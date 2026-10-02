<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\DTOs\FinanceCommissionEventData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use Carbon\CarbonImmutable;

final class ReconcileRewardSettlementsUseCase
{
    public function __construct(private readonly RewardRepositoryFactory $repositoryFactory, private readonly RewardFinancialGatewayInterface $gateway) {}

    /** @return array{confirmed: int, held: int, unavailable: int} */
    public function execute(int $limit): array
    {
        $result = ['confirmed' => 0, 'held' => 0, 'unavailable' => 0];
        $repository = $this->repositoryFactory->make();
        $rewards = $repository->listReconciliationCandidates($limit);
        foreach ($rewards as $reward) {
            try {
                $key = $reward->status === 'reversal_pending' || $reward->status === 'reversal_failed'
                    ? $repository->reversalIdempotencyKey((string) $reward->id)
                    : $reward->settlement_idempotency_key;
                if (! is_string($key) || $key === '') {
                    $repository->placeReconciliationHold((string) $reward->id, 'FINANCE_RECONCILIATION_MISSING_KEY', CarbonImmutable::now('UTC'));
                    $result['held']++;

                    continue;
                }
                $event = $this->gateway->findByIdempotencyKey($key);
                if ($event === null) {
                    if ($reward->status === 'reversal_pending' || $reward->status === 'reversal_failed') {
                        continue;
                    }
                    $repository->placeReconciliationHold((string) $reward->id, 'FINANCE_RECONCILIATION_EVENT_MISSING', CarbonImmutable::now('UTC'));
                    $result['held']++;

                    continue;
                }
                $type = $reward->status === 'reversal_pending' || $reward->status === 'reversal_failed' ? 'reversal' : (string) $reward->commission_type;
                if (! $this->matches($reward, $event, $type)) {
                    $repository->placeReconciliationHold((string) $reward->id, 'FINANCE_RECONCILIATION_MISMATCH', CarbonImmutable::now('UTC'));
                    $result['held']++;

                    continue;
                }
                $repository->confirmReconciliation($reward, $type, (string) $event->id, CarbonImmutable::now('UTC'));
                $result['confirmed']++;
            } catch (RewardSettlementException) {
                $result['unavailable']++;
            }
        }

        return $result;
    }

    private function matches(object $reward, FinanceCommissionEventData $event, string $type): bool
    {
        return $event->status === 'posted' && $event->commission_type === $type && $event->ib_user_id === $reward->beneficiary_user_id
            && $event->amount_minor === (int) $reward->amount_minor && $event->minor_units === (int) $reward->currency_precision
            && $event->reference_type === 'reward' && $event->reference_id === $reward->id;
    }
}
