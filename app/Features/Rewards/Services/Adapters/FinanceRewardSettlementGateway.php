<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\DTOs\RewardSettlementResultData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class FinanceRewardSettlementGateway implements RewardSettlementGatewayInterface
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function settle(RewardSettlementRequestData $request): RewardSettlementResultData
    {
        try {
            $response = Http::baseUrl((string) $this->config->get('finance.base_url'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) $this->config->get('finance.timeout_seconds', 5))
                ->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) $this->config->get('finance.internal_token'),
                    'X-Internal-Source' => (string) $this->config->get('finance.source_service'),
                ])->post('/api/finance/v1/ib/commission-events', [
                    'idempotency_key' => $request->idempotency_key,
                    'source_service' => (string) $this->config->get('finance.source_service'),
                    'ib_user_id' => $request->beneficiary_user_id,
                    'system_wallet_slug' => strtolower($request->currency_code).'-main',
                    'commission_type' => 'cpa',
                    'reason_code' => 'ib_reward_settlement',
                    'reason_label' => 'IB reward settlement',
                    'amount_minor' => $request->amount_minor,
                    'reference_type' => 'reward',
                    'reference_id' => $request->reward_id,
                    'source_client_id' => $request->reward_id,
                    'network_level' => 1,
                    'metadata' => ['reward_id' => $request->reward_id],
                ]);
        } catch (ConnectionException) {
            throw new RewardSettlementException('finance_unavailable');
        }

        if (! $response->successful()) {
            throw new RewardSettlementException($response->serverError() ? 'finance_unavailable' : 'finance_rejected');
        }

        $data = $response->json('data');
        if (! is_array($data) || ! $this->isValidResponse($data, $request)) {
            throw new RewardSettlementException('finance_contract_invalid');
        }

        return new RewardSettlementResultData('finance', (string) $data['event']['id']);
    }

    /** @param array<string, mixed> $data */
    private function isValidResponse(array $data, RewardSettlementRequestData $request): bool
    {
        $event = $data['event'] ?? null;

        return in_array($data['status'] ?? null, ['created', 'duplicate'], true)
            && is_array($event)
            && is_int($event['id'] ?? null)
            && ($event['status'] ?? null) === 'posted'
            && ($event['idempotency_key'] ?? null) === $request->idempotency_key
            && ($event['commission_type'] ?? null) === 'cpa'
            && ($event['ib_user_id'] ?? null) === $request->beneficiary_user_id
            && (int) ($event['amount_minor'] ?? -1) === $request->amount_minor
            && ($event['reference_type'] ?? null) === 'reward'
            && ($event['reference_id'] ?? null) === $request->reward_id
            && ($data['currency_code'] ?? null) === $request->currency_code
            && (int) ($data['minor_units'] ?? -1) === $request->currency_precision
            && ($data['system_wallet_slug'] ?? null) === strtolower($request->currency_code).'-main';
    }
}
