<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Data\V1\ApplyProgressionPlacementData;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use App\Features\Subscriptions\Contracts\Ports\Input\ApplyProgressionPlacementPort;
use Illuminate\Support\Str;

final class ApplyProgressionPlacementUseCase implements ApplyProgressionPlacementPort
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositoryFactory) {}

    public function apply(ApplyProgressionPlacementData $data): ProgressionPlacementOutcome
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $data): ProgressionPlacementOutcome {
            $subscription = $repository->findById($data->subscription_id);
            if ($subscription === null || $subscription->status !== SubscriptionStatus::Active) {
                return ProgressionPlacementOutcome::NotActive;
            }

            $outcome = ProgressionPlacementOutcome::from($subscription->applyProgressionPlacement($data->target_program_id, (string) Str::uuid7(), $data->operation_id, static fn (): string => (string) Str::uuid7(), $data->occurred_at));
            if ($outcome === ProgressionPlacementOutcome::Applied) {
                $repository->save($subscription, $subscription->lockVersion);
            }

            return $outcome;
        });
    }
}
