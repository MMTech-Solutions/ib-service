<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\NegativePnlCutSnapshotData;
use App\Features\Rewards\DTOs\PersistVolumeRewardData;
use Carbon\CarbonImmutable;

interface RewardRepositoryInterface
{
    public function findNegativePnlCut(string $identityKey): ?NegativePnlCutSnapshotData;

    public function freezeNegativePnlCut(NegativePnlCutSnapshotData $snapshot): NegativePnlCutSnapshotData;

    public function persistVolumeReward(PersistVolumeRewardData $data): bool;

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

    /** @param list<string> $excludedIds */
    public function claimNextSettlement(CarbonImmutable $now, CarbonImmutable $retryAt, CarbonImmutable $lockExpiresAt, array $excludedIds = []): ?object;

    public function releaseSettlementClaim(string $rewardId, string $token): void;

    public function markRewardSettled(string $rewardId, string $token, string $provider, string $referenceId, CarbonImmutable $at): bool;

    public function markRewardSettlementFailed(string $rewardId, string $token, string $errorCode, CarbonImmutable $at): bool;

    /** @return array{0: object, 1: object} */
    public function beginFinancialOperation(string $rewardId, string $actorId, string $type, string $reasonCode, ?string $reasonLabel, ?int $amountMinor, ?string $customKey): array;

    public function createCompensationReward(object $reward, object $operation, int $amountMinor, string $reasonCode, CarbonImmutable $at): string;

    public function completeFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, ?string $compensationRewardId, ?string $providerReferenceId, CarbonImmutable $at, string $token, ?string $outcome = null): void;

    public function failFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, string $errorCode, CarbonImmutable $at, string $token): void;

    public function placeReconciliationHold(string $rewardId, string $code, CarbonImmutable $at, ?string $token = null): void;

    public function findFinancialOperation(string $operationId): object;

    /** @param list<string> $excludedIds */
    public function claimNextReconciliation(CarbonImmutable $now, CarbonImmutable $expiresAt, array $excludedIds): ?object;

    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function freezeFinancialOperationRequest(string $rewardId, string $operationId, string $token, array $request): array;

    public function confirmReconciliation(object $reward, string $type, string $providerReferenceId, CarbonImmutable $at): void;
}
