<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ApplyForSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ApplyForSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\ApplyForSubscriptionUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ApplyForSubscriptionController
{
    use ApiResponse;

    public function __invoke(
        ApplyForSubscriptionRequest $request,
        ApplyForSubscriptionUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ApplyForSubscriptionCommand::fromRequest($request, $userContext));

        return $this->created(
            $subscription->toArray(),
            'Subscription application submitted successfully.',
        );
    }
}
