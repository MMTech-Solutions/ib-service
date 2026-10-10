<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionChangeHistoryData;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionChangesCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionChangesRequest;
use App\Features\Subscriptions\Catalog\UseCases\ListSubscriptionChangesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListSubscriptionChangesController
{
    use ApiResponse;

    public function __invoke(ListSubscriptionChangesRequest $request, ListSubscriptionChangesUseCase $useCase): JsonResponse
    {
        $command = ListSubscriptionChangesCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $entries = array_map(static fn (SubscriptionChangeHistoryData $entry): array => $entry->toArray(), $result->entries);
        $paginator = new LengthAwarePaginator($entries, $result->total, $command->query->perPage, $command->query->page, ['path' => $request->url(), 'pageName' => 'page']);
        $filters = $request->safe()->only(['action', 'actor_kind', 'occurred_at_from', 'occurred_at_to']);

        return $this->success(data: $entries, message: 'Subscription changes retrieved successfully.', meta: ['filters' => (object) $filters], paginator: $paginator);
    }
}
