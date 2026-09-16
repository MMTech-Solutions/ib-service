<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\CancelSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\CancelSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\CancelSubscriptionUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CancelSubscriptionController
{
    use ApiResponse;

    public function __invoke(
        CancelSubscriptionRequest $request,
        CancelSubscriptionUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(CancelSubscriptionCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription cancelled successfully.',
        );
    }
}
