<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\RejectSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\RejectSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\RejectSubscriptionUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class RejectSubscriptionController
{
    use ApiResponse;

    public function __invoke(
        RejectSubscriptionRequest $request,
        RejectSubscriptionUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(RejectSubscriptionCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription rejected successfully.',
        );
    }
}
