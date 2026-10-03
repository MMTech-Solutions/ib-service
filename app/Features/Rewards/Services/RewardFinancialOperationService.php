<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Rewards\Actions\BuildRewardFinancialRequestAction;
use App\Features\Rewards\Actions\ValidateRewardFinancialEventAction;
use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\DTOs\RewardFinancialOperationData;
use App\Features\Rewards\DTOs\RewardReversalRequestData;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use Carbon\CarbonImmutable;

final class RewardFinancialOperationService
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositories,
        private readonly RewardFinancialGatewayInterface $financial,
        private readonly RewardSettlementGatewayInterface $settlement,
        private readonly ValidateRewardFinancialEventAction $validate,
        private readonly BuildRewardFinancialRequestAction $requests,
        private readonly ResolveModulesPort $modules,
        private readonly ResolvePlanSubscriptionContextPort $plans,
    ) {}

    public function execute(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        [$reward, $operation] = $this->repositories->make()->beginFinancialOperation($data->reward_id, $data->actor_user_id, $data->operation_type, $data->reason_code, $data->reason_label, $data->amount_minor, $data->idempotency_key);
        if ($operation->status !== 'completed') {
            $this->recover($reward, $operation);
        }

        return $this->present($this->repositories->make()->findFinancialOperation((string) $operation->id));
    }

    public function recover(object $reward, object $operation): void
    {
        $repository = $this->repositories->make();
        $rewardStatus = $operation->operation_type === 'reversal' ? 'reversal_failed' : null;
        try {
            $request = $this->requests->settlement($reward);
            if ($request->network_level < 1) {
                throw new RewardSettlementException('finance_legacy_level_invalid');
            }
            $reference = null;
            $compensationId = null;
            $outcome = null;
            if ($operation->operation_type === 'cancellation') {
                $event = $this->financial->findByIdempotencyKey($request->idempotency_key);
                if ($event !== null) {
                    if (! $this->validate->matches($reward, $event)) {
                        throw new RewardSettlementException('finance_reconciliation_mismatch');
                    }
                    $reference = (string) $event->id;
                } elseif ((int) $reward->settlement_attempt_count > 0) {
                    $this->assertPayable($reward);
                    $frozen = $repository->freezeFinancialOperationRequest((string) $reward->id, (string) $operation->id, (string) $operation->lock_token, get_object_vars($request));
                    $reference = $this->settlement->settle(new RewardSettlementRequestData(...$frozen))->reference_id;
                }
                $rewardStatus = $reference === null ? 'cancelled' : 'settled';
                $outcome = $reference === null ? 'cancelled' : 'rejected_already_settled';
            } elseif ($operation->operation_type === 'reversal') {
                $reversal = new RewardReversalRequestData((string) $reward->id, $request->beneficiary_user_id, $request->amount_minor, $request->currency_code, $request->currency_precision, (string) $operation->idempotency_key, (string) $reward->settlement_reference_id, (string) $operation->reason_code, $operation->reason_label, $request->network_level);
                $frozen = $repository->freezeFinancialOperationRequest((string) $reward->id, (string) $operation->id, (string) $operation->lock_token, get_object_vars($reversal));
                $reference = $this->financial->reverse(new RewardReversalRequestData(...$frozen))->reference_id;
                $rewardStatus = 'reversed';
            } else {
                $this->assertPayable($reward);
                $compensationId = $repository->createCompensationReward($reward, $operation, (int) $operation->amount_minor, (string) $operation->reason_code, CarbonImmutable::now('UTC'));
                $operation = $repository->findFinancialOperation((string) $operation->id);
                $reference = $this->settlement->settle(new RewardSettlementRequestData(...json_decode($operation->request_snapshot, true, 512, JSON_THROW_ON_ERROR)))->reference_id;
                $rewardStatus = null;
            }
            $repository->completeFinancialOperation((string) $reward->id, (string) $operation->id, $rewardStatus, $compensationId, $reference, CarbonImmutable::now('UTC'), (string) $operation->lock_token, $outcome);
        } catch (RewardSettlementException $exception) {
            if ($exception->error_code === 'financial_lease_lost') {
                return;
            }
            try {
                if (in_array($exception->error_code, ['finance_contract_invalid', 'finance_reconciliation_mismatch', 'finance_legacy_level_invalid'], true)) {
                    $repository->placeReconciliationHold((string) $reward->id, strtoupper($exception->error_code), CarbonImmutable::now('UTC'), (string) $operation->lock_token);
                }
                $repository->failFinancialOperation((string) $reward->id, (string) $operation->id, $operation->operation_type === 'reversal' ? 'reversal_failed' : null, $exception->error_code, CarbonImmutable::now('UTC'), (string) $operation->lock_token);
            } catch (RewardSettlementException) {
                return;
            }
        }
    }

    private function assertPayable(object $reward): void
    {
        if ($reward->commission_type === 'pnl' && ! config('rewards.negative_pnl.settlement_enabled', true)) {
            throw new RewardSettlementException('pnl_settlement_disabled');
        }
        $module = $this->modules->findByIds([(string) $reward->module_id])[0] ?? null;
        $plan = $this->plans->resolve(new ResolvePlanSubscriptionContextQueryData((string) $reward->plan_id));
        if ($module === null || ! $module->is_active || $module->processing_status !== 'running' || ! $plan->is_active || $plan->archived) {
            throw new RewardSettlementException('financial_operation_paused');
        }
    }

    public function recoverSettlement(object $reward): string
    {
        $this->assertPayable($reward);

        return $this->settlement->settle($this->requests->settlement($reward))->reference_id;
    }

    private function present(object $operation): RewardFinancialOperationData
    {
        return new RewardFinancialOperationData((string) $operation->reward_id, (string) $operation->id, (string) $operation->operation_type, (string) $operation->status, $operation->compensation_reward_id, $operation->outcome);
    }
}
