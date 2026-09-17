<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ReleaseSubscriptionPlacementCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ReleaseSubscriptionPlacementRequest;
use App\Features\Subscriptions\Catalog\UseCases\ReleaseSubscriptionPlacementUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReleaseSubscriptionPlacementController
{
    use ApiResponse;

    public function __invoke(
        ReleaseSubscriptionPlacementRequest $request,
        ReleaseSubscriptionPlacementUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ReleaseSubscriptionPlacementCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription placement released successfully.',
        );
    }
}
