<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Actions\ValidateRewardFinancialEventAction;
use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Services\RewardFinancialOperationService;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Carbon\CarbonImmutable;

final class ReconcileRewardSettlementsUseCase
{
    public function __construct(private readonly RewardRepositoryFactory $repositoryFactory, private readonly RewardFinancialGatewayInterface $gateway, private readonly ValidateRewardFinancialEventAction $validateEvent, private readonly RewardFinancialOperationService $operations) {}

    /** @return array{confirmed: int, held: int, unavailable: int} */
    public function execute(int $limit): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.settlement.claim_lease_seconds']);
        $result = ['confirmed' => 0, 'held' => 0, 'unavailable' => 0];
        $repository = $this->repositoryFactory->make();
        $excluded = [];
        for ($index = 0; $index < $limit; $index++) {
            $now = CarbonImmutable::now('UTC');
            $reward = $repository->claimNextReconciliation($now, $now->addSeconds(max((int) $settings->get('rewards.settlement.claim_lease_seconds'), 1)), $excluded);
            if ($reward === null) {
                break;
            }
            $excluded[] = (string) $reward->id;
            try {
                if ($reward->reconciliation_operation_id !== null) {
                    $operation = $repository->findFinancialOperation($reward->reconciliation_operation_id);
                    $this->operations->recover($reward, $operation);
                    $operation = $repository->findFinancialOperation((string) $operation->id);
                    $operation->status === 'completed' ? $result['confirmed']++ : $result['unavailable']++;

                    continue;
                }
                $event = $this->gateway->findByIdempotencyKey((string) $reward->settlement_idempotency_key);
                if ($event !== null && $this->validateEvent->matches($reward, $event)) {
                    $repository->confirmReconciliation($reward, (string) $reward->commission_type, (string) $event->id, CarbonImmutable::now('UTC'));
                    $result['confirmed']++;
                } elseif ($event === null && $reward->reconciliation_hold_at === null) {
                    $reference = $this->operations->recoverSettlement($reward);
                    $repository->confirmReconciliation($reward, (string) $reward->commission_type, $reference, CarbonImmutable::now('UTC'));
                    $result['confirmed']++;
                } else {
                    $repository->placeReconciliationHold((string) $reward->id, $event === null ? 'FINANCE_RECONCILIATION_EVENT_MISSING' : 'FINANCE_RECONCILIATION_MISMATCH', CarbonImmutable::now('UTC'), (string) $reward->settlement_lock_token);
                    $repository->releaseSettlementClaim((string) $reward->id, (string) $reward->settlement_lock_token);
                    $result['held']++;
                }
            } catch (RewardSettlementException $exception) {
                try {
                    if ($exception->error_code === 'finance_contract_invalid') {
                        $repository->placeReconciliationHold((string) $reward->id, 'FINANCE_CONTRACT_INVALID', CarbonImmutable::now('UTC'), (string) $reward->settlement_lock_token);
                    }
                } catch (RewardSettlementException) {
                }
                $repository->releaseSettlementClaim((string) $reward->id, (string) $reward->settlement_lock_token);
                $result['unavailable']++;
            }
        }

        return $result;
    }
}
