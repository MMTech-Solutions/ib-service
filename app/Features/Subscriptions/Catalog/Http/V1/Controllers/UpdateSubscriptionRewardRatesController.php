<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\UpdateSubscriptionRewardRatesCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\UpdateSubscriptionRewardRatesRequest;
use App\Features\Subscriptions\Catalog\UseCases\UpdateSubscriptionRewardRatesUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateSubscriptionRewardRatesController
{
    use ApiResponse;

    public function __invoke(
        UpdateSubscriptionRewardRatesRequest $request,
        UpdateSubscriptionRewardRatesUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(UpdateSubscriptionRewardRatesCommand::fromRequest($request, $userContext));

        return $this->success($subscription->toArray(), 'Subscription reward rates updated successfully.');
    }
}
