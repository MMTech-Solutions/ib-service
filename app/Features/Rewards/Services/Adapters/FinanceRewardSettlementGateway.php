<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\FinanceCommissionEventData;
use App\Features\Rewards\DTOs\RewardReversalRequestData;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\DTOs\RewardSettlementResultData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class FinanceRewardSettlementGateway implements RewardFinancialGatewayInterface, RewardSettlementGatewayInterface
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
                    'commission_type' => $request->commission_type,
                    'reason_code' => 'ib_reward_settlement',
                    'reason_label' => 'IB reward settlement',
                    'amount_minor' => $request->amount_minor,
                    'reference_type' => 'reward',
                    'reference_id' => $request->reward_id,
                    'source_client_id' => $request->reward_id,
                    'network_level' => $request->network_level,
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

    public function reverse(RewardReversalRequestData $request): RewardSettlementResultData
    {
        $data = $this->postCommissionEvent([
            'idempotency_key' => $request->idempotency_key,
            'ib_user_id' => $request->beneficiary_user_id,
            'system_wallet_slug' => strtolower($request->currency_code).'-main',
            'commission_type' => 'reversal',
            'reason_code' => $request->reason_code,
            'reason_label' => $request->reason_label,
            'amount_minor' => $request->amount_minor,
            'reference_type' => 'reward',
            'reference_id' => $request->reward_id,
            'source_client_id' => $request->reward_id,
            'network_level' => $request->network_level,
            'reverses_commission_event_id' => (int) $request->original_finance_event_id,
            'metadata' => ['reward_id' => $request->reward_id],
        ]);

        if (! $this->isValidEventResponse($data, $request->idempotency_key, 'reversal', $request->beneficiary_user_id, $request->amount_minor, $request->currency_code, $request->currency_precision, $request->reward_id, $request->network_level)
            || (int) ($data['event']['reverses_commission_event_id'] ?? 0) !== (int) $request->original_finance_event_id) {
            throw new RewardSettlementException('finance_contract_invalid');
        }

        return new RewardSettlementResultData('finance', (string) $data['event']['id']);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?FinanceCommissionEventData
    {
        try {
            $response = $this->client()->get('/api/finance/v1/ib/commission-events', ['idempotency_key' => $idempotencyKey, 'per_page' => 1]);
        } catch (ConnectionException) {
            throw new RewardSettlementException('finance_unavailable');
        }

        if (! $response->successful()) {
            throw new RewardSettlementException($response->serverError() ? 'finance_unavailable' : 'finance_rejected');
        }

        $events = $response->json('data');
        if (! is_array($events) || ! array_is_list($events) || count($events) > 1) {
            throw new RewardSettlementException('finance_contract_invalid');
        }
        if ($events === []) {
            return null;
        }
        $event = $events[0];
        if (! is_array($event) || ! is_int($event['id'] ?? null) || $event['id'] < 1
            || ! is_int($event['amount_minor'] ?? null) || ! is_int($event['minor_units'] ?? null)
            || ! is_int($event['network_level'] ?? null) || $event['network_level'] < 1
            || (isset($event['reverses_commission_event_id']) && ! is_int($event['reverses_commission_event_id']))) {
            throw new RewardSettlementException('finance_contract_invalid');
        }
        foreach (['idempotency_key', 'commission_type', 'ib_user_id', 'reference_type', 'reference_id', 'status', 'currency_code', 'system_wallet_slug'] as $field) {
            if (! is_string($event[$field] ?? null) || $event[$field] === '') {
                throw new RewardSettlementException('finance_contract_invalid');
            }
        }

        return new FinanceCommissionEventData(
            id: $event['id'], idempotency_key: (string) ($event['idempotency_key'] ?? ''), commission_type: (string) ($event['commission_type'] ?? ''),
            ib_user_id: (string) ($event['ib_user_id'] ?? ''), amount_minor: (int) ($event['amount_minor'] ?? -1),
            minor_units: (int) ($event['minor_units'] ?? -1), reference_type: (string) ($event['reference_type'] ?? ''),
            reference_id: (string) ($event['reference_id'] ?? ''), status: (string) ($event['status'] ?? ''),
            reverses_commission_event_id: isset($event['reverses_commission_event_id']) ? (int) $event['reverses_commission_event_id'] : null,
            currency_code: (string) ($event['currency_code'] ?? ''), system_wallet_slug: (string) ($event['system_wallet_slug'] ?? ''),
            network_level: is_int($event['network_level'] ?? null) ? $event['network_level'] : -1,
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function postCommissionEvent(array $payload): array
    {
        try {
            $response = $this->client()->post('/api/finance/v1/ib/commission-events', array_merge([
                'source_service' => (string) $this->config->get('finance.source_service'),
            ], $payload));
        } catch (ConnectionException) {
            throw new RewardSettlementException('finance_unavailable');
        }
        if (! $response->successful()) {
            throw new RewardSettlementException($response->serverError() ? 'finance_unavailable' : 'finance_rejected');
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RewardSettlementException('finance_contract_invalid');
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) $this->config->get('finance.base_url'))
            ->acceptJson()->asJson()->timeout((int) $this->config->get('finance.timeout_seconds', 5))->connectTimeout(3)
            ->withHeaders(['X-Internal-Token' => (string) $this->config->get('finance.internal_token'), 'X-Internal-Source' => (string) $this->config->get('finance.source_service')]);
    }

    /** @param array<string, mixed> $data */
    private function isValidEventResponse(array $data, string $key, string $type, string $beneficiary, int $amount, string $currency, int $precision, string $rewardId, int $networkLevel): bool
    {
        $event = $data['event'] ?? null;

        return in_array($data['status'] ?? null, ['created', 'duplicate'], true) && is_array($event) && is_int($event['id'] ?? null)
            && ($event['status'] ?? null) === 'posted' && ($event['idempotency_key'] ?? null) === $key && ($event['commission_type'] ?? null) === $type
            && ($event['ib_user_id'] ?? null) === $beneficiary && ($event['amount_minor'] ?? null) === $amount
            && ($event['network_level'] ?? null) === $networkLevel
            && ($event['reference_type'] ?? null) === 'reward' && ($event['reference_id'] ?? null) === $rewardId
            && ($data['currency_code'] ?? null) === $currency && ($data['minor_units'] ?? null) === $precision
            && ($data['system_wallet_slug'] ?? null) === strtolower($currency).'-main';
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
            && ($event['commission_type'] ?? null) === $request->commission_type
            && ($event['ib_user_id'] ?? null) === $request->beneficiary_user_id
            && ($event['amount_minor'] ?? null) === $request->amount_minor
            && ($event['network_level'] ?? null) === $request->network_level
            && ($event['reference_type'] ?? null) === 'reward'
            && ($event['reference_id'] ?? null) === $request->reward_id
            && ($data['currency_code'] ?? null) === $request->currency_code
            && ($data['minor_units'] ?? null) === $request->currency_precision
            && ($data['system_wallet_slug'] ?? null) === strtolower($request->currency_code).'-main';
    }
}
