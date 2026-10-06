<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Programs\Contracts\Data\V1\NegativePnlModuleConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Rewards\Contracts\Data\V1\RecordNegativePnlClosureData;
use App\Features\Rewards\DTOs\NegativePnlAccountCutData;
use App\Features\Rewards\DTOs\NegativePnlAggregateData;
use App\Features\Rewards\DTOs\NegativePnlFrozenInputsData;
use App\Features\Rewards\DTOs\NegativePnlProcessingPeriodData;
use App\Features\Rewards\DTOs\NegativePnlWorkData;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;
use Closure;

interface NegativePnlProcessingRepositoryInterface
{
    /** @return array{configuration_after: ?string, subscription_after: ?string, started_at: ?string} */
    public function discoveryCursor(): array;

    public function saveDiscoveryCursor(?string $configurationAfter, ?string $subscriptionAfter, string $startedAt): void;

    public function finishDiscovery(string $startedAt): void;

    public function countPendingClosures(): int;

    public function transactionForLease(NegativePnlWorkData $work, Closure $callback): void;

    public function seed(NegativePnlSubscriptionData $subscription, NegativePnlProgramConfigurationData $configuration, NegativePnlModuleConfigurationData $group): void;

    public function recordClosure(RecordNegativePnlClosureData $data): void;

    /** @param list<string> $excluded */
    public function claim(string $at, int $leaseSeconds, array $excluded): ?NegativePnlWorkData;

    public function pendingPeriod(NegativePnlWorkData $work): ?NegativePnlProcessingPeriodData;

    public function beginPeriod(NegativePnlWorkData $work, NegativePnlFrozenInputsData $inputs): NegativePnlProcessingPeriodData;

    /** @param list<NegativePnlAccountCutData> $periods */
    public function recordReceipt(NegativePnlWorkData $work, string $periodId, string $referralId, array $periods): void;

    public function ready(NegativePnlWorkData $work, string $periodId): NegativePnlProcessingPeriodData;

    public function persistReward(NegativePnlWorkData $work, NegativePnlProcessingPeriodData $period, NegativePnlAggregateData $aggregate, PositiveMoney $amount): bool;

    public function recordOutcome(NegativePnlWorkData $work, string $periodId, string $referralId, string $accountId, string $reason): void;

    public function recordAggregateOutcome(NegativePnlWorkData $work, string $periodId, NegativePnlAggregateData $aggregate, string $reason): void;

    public function complete(NegativePnlWorkData $work, string $periodId): void;

    public function release(NegativePnlWorkData $work, ?string $error, bool $restartInterval = false, bool $finished = false): void;

    public function reset(NegativePnlWorkData $work, string $at): NegativePnlWorkData;
}
