<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionData;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionsCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionsRequest;
use App\Features\Subscriptions\Catalog\UseCases\ListSubscriptionsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListSubscriptionsController
{
    use ApiResponse;

    public function __invoke(ListSubscriptionsRequest $request, ListSubscriptionsUseCase $useCase): JsonResponse
    {
        $command = ListSubscriptionsCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $subscriptions = array_map(
            static fn (SubscriptionData $subscription): array => $subscription->toArray(),
            $result->subscriptions,
        );
        $paginator = new LengthAwarePaginator(
            items: $subscriptions,
            total: $result->total,
            perPage: $result->perPage,
            currentPage: $result->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter(
            $request->safe()->only(['plan_id', 'status', 'external_user_id']),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->success(
            data: $subscriptions,
            message: 'Subscriptions retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
