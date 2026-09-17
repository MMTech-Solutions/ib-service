<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\FixSubscriptionPlacementCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\FixSubscriptionPlacementRequest;
use App\Features\Subscriptions\Catalog\UseCases\FixSubscriptionPlacementUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class FixSubscriptionPlacementController
{
    use ApiResponse;

    public function __invoke(
        FixSubscriptionPlacementRequest $request,
        FixSubscriptionPlacementUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(FixSubscriptionPlacementCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription placement fixed successfully.',
        );
    }
}
