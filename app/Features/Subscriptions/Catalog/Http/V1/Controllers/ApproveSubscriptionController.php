<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ApproveSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ApproveSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\ApproveSubscriptionUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ApproveSubscriptionController
{
    use ApiResponse;

    public function __invoke(
        ApproveSubscriptionRequest $request,
        ApproveSubscriptionUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ApproveSubscriptionCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription approved successfully.',
        );
    }
}
