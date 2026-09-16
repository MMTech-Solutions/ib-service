<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ChangeSubscriptionPlanCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ChangeSubscriptionPlanRequest;
use App\Features\Subscriptions\Catalog\UseCases\ChangeSubscriptionPlanUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ChangeSubscriptionPlanController
{
    use ApiResponse;

    public function __invoke(
        ChangeSubscriptionPlanRequest $request,
        ChangeSubscriptionPlanUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ChangeSubscriptionPlanCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription plan changed successfully.',
        );
    }
}
