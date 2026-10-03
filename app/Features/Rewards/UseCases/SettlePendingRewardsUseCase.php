<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use Carbon\CarbonImmutable;

final class SettlePendingRewardsUseCase
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositoryFactory,
        private readonly RewardSettlementGatewayInterface $gateway,
        private readonly ResolveModulesPort $modules,
        private readonly ResolvePlanSubscriptionContextPort $plans,
    ) {}

    /** @return array{settled: int, failed: int, skipped: int} */
    public function execute(int $limit): array
    {
        $result = ['settled' => 0, 'failed' => 0, 'skipped' => 0];
        $repository = $this->repositoryFactory->make();
        $excludedIds = [];

        for ($processed = 0; $processed < $limit;) {
            $claim = $this->claimNext($repository, $excludedIds);
            if ($claim === null) {
                break;
            }
            try {
                $module = $this->modules->findByIds([(string) $claim->module_id])[0] ?? null;
                $plan = $this->plans->resolve(new ResolvePlanSubscriptionContextQueryData((string) $claim->plan_id));
            } catch (\Throwable) {
                $repository->releaseSettlementClaim((string) $claim->id, (string) $claim->settlement_lock_token);
                $excludedIds[] = (string) $claim->id;
                $result['skipped']++;

                continue;
            }
            if ($module === null || ! $module->is_active || $module->processing_status !== 'running'
                || ! $plan->is_active || $plan->archived) {
                $repository->releaseSettlementClaim((string) $claim->id, (string) $claim->settlement_lock_token);
                $excludedIds[] = (string) $claim->id;
                $result['skipped']++;

                continue;
            }

            $processed++;

            try {
                $settlement = $this->gateway->settle(new RewardSettlementRequestData(...json_decode($claim->settlement_request_snapshot, true, 512, JSON_THROW_ON_ERROR)));
                $confirmed = $repository->markRewardSettled((string) $claim->id, (string) $claim->settlement_lock_token, $settlement->provider, $settlement->reference_id, CarbonImmutable::now('UTC'));
                $confirmed ? $result['settled']++ : $result['skipped']++;
            } catch (RewardSettlementException $exception) {
                if ($exception->error_code === 'finance_contract_invalid') {
                    try {
                        $repository->placeReconciliationHold((string) $claim->id, 'FINANCE_EVENT_MISMATCH', CarbonImmutable::now('UTC'), (string) $claim->settlement_lock_token);
                    } catch (RewardSettlementException) {
                        $result['skipped']++;

                        continue;
                    }
                }
                $confirmed = $repository->markRewardSettlementFailed((string) $claim->id, (string) $claim->settlement_lock_token, $exception->error_code, CarbonImmutable::now('UTC'));
                $confirmed ? $result['failed']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    private function claimNext(RewardRepositoryInterface $repository, array $excludedIds): ?object
    {
        $now = CarbonImmutable::now('UTC');
        $retryAt = $now->subSeconds((int) config('rewards.settlement.retry_delay_seconds', 300));
        $lockExpiresAt = $now->addSeconds((int) config('rewards.settlement.claim_lease_seconds', 60));

        return $repository->claimNextSettlement($now, $retryAt, $lockExpiresAt, $excludedIds);
    }
}
