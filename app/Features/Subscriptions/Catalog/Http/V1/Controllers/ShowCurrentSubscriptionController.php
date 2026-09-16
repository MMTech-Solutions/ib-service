<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ShowCurrentSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ShowCurrentSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\ShowCurrentSubscriptionUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowCurrentSubscriptionController
{
    use ApiResponse;

    public function __invoke(
        ShowCurrentSubscriptionRequest $request,
        ShowCurrentSubscriptionUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ShowCurrentSubscriptionCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Current subscription retrieved successfully.',
        );
    }
}
