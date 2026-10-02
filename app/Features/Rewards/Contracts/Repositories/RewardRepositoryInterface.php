<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Repositories;

use App\Features\Rewards\DTOs\CaptureCpaContextData;
use Carbon\CarbonImmutable;

interface RewardRepositoryInterface
{
    public function findCpaContextId(CaptureCpaContextData $data): ?string;

    /** @param array<int, object> $symbols @param array<string, mixed> $requirements */
    /** @return array{id: string, created: bool} */
    public function captureCpaContext(CaptureCpaContextData $data, object $subscription, object $rule, array $symbols, array $requirements): array;

    /** @return array<int, object> */
    public function listCpaContextsWithoutReward(int $limit): array;

    /** @param array<string, mixed> $requirements */
    public function updateCpaProgress(string $contextId, string $volume, int $depositMinor, CarbonImmutable $cutoff, string $status, ?string $errorCode, ?array $requirements = null): void;

    /** @param array<string, mixed> $requirements */
    public function persistQualifiedCpaContext(object $context, array $requirements, object $evidence, string $volume, int $depositMinor, CarbonImmutable $cutoff, bool $qualified): void;

    public function claimNextSettlement(CarbonImmutable $now, CarbonImmutable $retryAt, CarbonImmutable $lockExpiresAt): ?object;

    public function markRewardSettled(string $rewardId, string $token, string $provider, string $referenceId, CarbonImmutable $at): void;

    public function markRewardSettlementFailed(string $rewardId, string $token, string $errorCode, CarbonImmutable $at): void;

    /** @return array{0: object, 1: object} */
    public function beginFinancialOperation(string $rewardId, string $actorId, string $type, string $reasonCode, ?string $reasonLabel, ?int $amountMinor, ?string $customKey): array;

    public function createCompensationReward(object $reward, object $operation, int $amountMinor, string $reasonCode, CarbonImmutable $at): string;

    public function markCompensationSettled(string $rewardId, string $provider, string $referenceId, CarbonImmutable $at): void;

    public function markCompensationFailed(string $rewardId, string $errorCode, CarbonImmutable $at): void;

    public function completeFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, ?string $compensationRewardId, ?string $providerReferenceId, CarbonImmutable $at): void;

    public function failFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, string $errorCode, CarbonImmutable $at): void;

    public function placeReconciliationHold(string $rewardId, string $code, CarbonImmutable $at): void;

    public function findFinancialOperation(string $operationId): object;

    /** @return array<int, object> */
    public function listReconciliationCandidates(int $limit): array;

    public function reversalIdempotencyKey(string $rewardId): ?string;

    public function confirmReconciliation(object $reward, string $type, string $providerReferenceId, CarbonImmutable $at): void;
}
