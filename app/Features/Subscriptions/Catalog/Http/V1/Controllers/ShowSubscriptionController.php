<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ShowSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ShowSubscriptionRequest;
use App\Features\Subscriptions\Catalog\UseCases\ShowSubscriptionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowSubscriptionController
{
    use ApiResponse;

    public function __invoke(ShowSubscriptionRequest $request, ShowSubscriptionUseCase $useCase): JsonResponse
    {
        $subscription = $useCase->execute(ShowSubscriptionCommand::fromRequest($request));

        return $this->success(
            $subscription->toArray(),
            'Subscription retrieved successfully.',
        );
    }
}
