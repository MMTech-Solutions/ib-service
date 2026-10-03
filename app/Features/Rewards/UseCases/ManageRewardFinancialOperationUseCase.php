<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Actions\ValidateRewardFinancialEventAction;
use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\DTOs\RewardFinancialOperationData;
use App\Features\Rewards\DTOs\RewardReversalRequestData;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\Exceptions\RewardFinancialOperationNotAllowedException;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use Carbon\CarbonImmutable;

final class ManageRewardFinancialOperationUseCase
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositoryFactory,
        private readonly RewardFinancialGatewayInterface $financialGateway,
        private readonly RewardSettlementGatewayInterface $settlementGateway,
        private readonly ValidateRewardFinancialEventAction $validateFinancialEvent,
    ) {}

    public function execute(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        return match ($data->operation_type) {
            'cancellation' => $this->cancel($data),
            'reversal' => $this->reverse($data),
            'compensation' => $this->compensate($data),
            default => throw RewardFinancialOperationNotAllowedException::create(),
        };
    }

    private function cancel(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        [$reward, $operation] = $this->start($data->reward_id, $data->actor_user_id, 'cancellation', $data->reason_code, $data->reason_label, null, null);
        if ($operation->status === 'completed') {
            return $this->present($operation);
        }

        try {
            $event = $this->financialGateway->findByIdempotencyKey((string) $reward->settlement_idempotency_key);
            if ($event !== null) {
                if (! $this->validateFinancialEvent->matches($reward, $event)) {
                    $this->hold($data->reward_id, 'FINANCE_RECONCILIATION_MISMATCH');
                    throw new RewardSettlementException('finance_reconciliation_mismatch');
                }
                $this->complete($data->reward_id, (string) $operation->id, 'settled', null, (string) $event->id);
            } else {
                $this->complete($data->reward_id, (string) $operation->id, 'cancelled');
            }
        } catch (RewardSettlementException $exception) {
            $this->fail($data->reward_id, (string) $operation->id, 'failed', $exception->error_code);
        }

        return $this->operation((string) $operation->id);
    }

    private function reverse(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        [$reward, $operation] = $this->start($data->reward_id, $data->actor_user_id, 'reversal', $data->reason_code, $data->reason_label, null, null);
        if ($operation->status === 'completed') {
            return $this->present($operation);
        }

        try {
            $result = $this->financialGateway->reverse(new RewardReversalRequestData(
                reward_id: (string) $reward->id, beneficiary_user_id: (string) $reward->beneficiary_user_id,
                amount_minor: (int) $reward->amount_minor, currency_code: (string) $reward->currency_code,
                currency_precision: (int) $reward->currency_precision, idempotency_key: (string) $operation->idempotency_key,
                original_finance_event_id: (string) $reward->settlement_reference_id, reason_code: $data->reason_code, reason_label: $data->reason_label,
                network_level: (int) $reward->network_level,
            ));
            $this->complete($data->reward_id, (string) $operation->id, 'reversed', null, $result->reference_id);
        } catch (RewardSettlementException $exception) {
            $this->fail($data->reward_id, (string) $operation->id, 'reversal_failed', $exception->error_code);
        }

        return $this->operation((string) $operation->id);
    }

    private function compensate(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        $amountMinor = $data->amount_minor ?? 0;
        [$reward, $operation] = $this->start($data->reward_id, $data->actor_user_id, 'compensation', $data->reason_code, $data->reason_label, $amountMinor, $data->idempotency_key);
        if ($operation->status === 'completed') {
            return $this->present($operation);
        }

        $compensationId = $operation->compensation_reward_id;
        try {
            if ($compensationId === null) {
                $compensationId = $this->repository()->createCompensationReward($reward, $operation, $amountMinor, $data->reason_code, CarbonImmutable::now('UTC'));
            }
            $result = $this->settlementGateway->settle(new RewardSettlementRequestData(
                reward_id: $compensationId, beneficiary_user_id: (string) $reward->beneficiary_user_id, amount_minor: $amountMinor,
                currency_code: (string) $reward->currency_code, currency_precision: (int) $reward->currency_precision,
                idempotency_key: 'ib-service:reward:'.$compensationId.':settlement',
                commission_type: (string) $reward->commission_type,
                network_level: (int) $reward->network_level,
            ));
            $this->repository()->markCompensationSettled($compensationId, $result->provider, $result->reference_id, CarbonImmutable::now('UTC'));
            $this->complete($data->reward_id, (string) $operation->id, null, $compensationId);
        } catch (RewardSettlementException $exception) {
            if ($compensationId !== null) {
                $this->repository()->markCompensationFailed($compensationId, $exception->error_code, CarbonImmutable::now('UTC'));
            }
            $this->fail($data->reward_id, (string) $operation->id, null, $exception->error_code);
        }

        return $this->operation((string) $operation->id);
    }

    /** @return array{0: object, 1: object} */
    private function start(string $rewardId, string $actorId, string $type, string $reasonCode, ?string $reasonLabel, ?int $amount, ?string $customKey): array
    {
        return $this->repository()->beginFinancialOperation($rewardId, $actorId, $type, $reasonCode, $reasonLabel, $amount, $customKey);
    }

    private function complete(string $rewardId, string $operationId, ?string $rewardStatus = null, ?string $compensationId = null, ?string $providerReference = null): void
    {
        $this->repository()->completeFinancialOperation($rewardId, $operationId, $rewardStatus, $compensationId, $providerReference, CarbonImmutable::now('UTC'));
    }

    private function fail(string $rewardId, string $operationId, ?string $rewardStatus, string $errorCode): void
    {
        $this->repository()->failFinancialOperation($rewardId, $operationId, $rewardStatus, $errorCode, CarbonImmutable::now('UTC'));
    }

    private function hold(string $rewardId, string $code): void
    {
        $this->repository()->placeReconciliationHold($rewardId, $code, CarbonImmutable::now('UTC'));
    }

    private function operation(string $operationId): RewardFinancialOperationData
    {
        return $this->present($this->repository()->findFinancialOperation($operationId));
    }

    private function present(object $operation): RewardFinancialOperationData
    {
        return new RewardFinancialOperationData((string) $operation->reward_id, (string) $operation->id, (string) $operation->operation_type, (string) $operation->status, $operation->compensation_reward_id === null ? null : (string) $operation->compensation_reward_id);
    }

    private function repository(): RewardRepositoryInterface
    {
        return $this->repositoryFactory->make();
    }
}
