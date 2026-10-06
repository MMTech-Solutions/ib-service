<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\DTOs\CaptureNegativePnlCutData;
use App\Features\Rewards\DTOs\NegativePnlCutSnapshotData;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use Carbon\CarbonImmutable;

final class CaptureNegativePnlCutService
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositoryFactory,
        private readonly ResolveNegativePnlPeriodsPort $provider,
    ) {}

    public function execute(CaptureNegativePnlCutData $data, ?NegativePnlPeriodData $resolvedPeriod = null): NegativePnlCutSnapshotData
    {
        if (! in_array($data->cadence, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        $identity = hash('sha256', json_encode([
            $data->module_id, $data->subscription_id, $data->account_id, $data->server_group_id,
            $data->cadence, CarbonImmutable::parse($data->query->occurred_from)->utc()->toISOString(), CarbonImmutable::parse($data->query->occurred_until)->utc()->toISOString(),
        ], JSON_THROW_ON_ERROR));
        $repository = $this->repositoryFactory->make();
        $existing = $repository->findNegativePnlCut($identity);
        if ($existing !== null) {
            if ($existing->period->external_user_id !== collect($data->query->subjects)->firstWhere('external_user_id', $existing->period->external_user_id)?->external_user_id) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }

            return $existing;
        }
        $period = $resolvedPeriod ?? collect($this->provider->resolve($data->query)->periods)->firstWhere('trading_account_id', $data->account_id);
        if ($period === null || $period->server_group_id !== $data->server_group_id
            || ! collect($data->query->subjects)->contains('external_user_id', $period->external_user_id)
            || $period->trading_account_id !== $data->account_id
            || ! CarbonImmutable::parse($period->occurred_from)->equalTo(CarbonImmutable::parse($data->query->occurred_from))
            || ! CarbonImmutable::parse($period->occurred_until)->equalTo(CarbonImmutable::parse($data->query->occurred_until))) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }

        return $repository->freezeNegativePnlCut(new NegativePnlCutSnapshotData(
            $identity, $data->module_id, $data->subscription_id, $data->account_id,
            $data->server_group_id, $data->cadence, $period,
        ));
    }
}
