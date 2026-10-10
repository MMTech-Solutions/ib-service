<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionPlacementHistoryData;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionPlacementsCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionPlacementsRequest;
use App\Features\Subscriptions\Catalog\UseCases\ListSubscriptionPlacementsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListSubscriptionPlacementsController
{
    use ApiResponse;

    public function __invoke(ListSubscriptionPlacementsRequest $request, ListSubscriptionPlacementsUseCase $useCase): JsonResponse
    {
        $command = ListSubscriptionPlacementsCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $entries = array_map(static fn (SubscriptionPlacementHistoryData $entry): array => $entry->toArray(), $result->entries);
        $paginator = new LengthAwarePaginator($entries, $result->total, $command->query->perPage, $command->query->page, ['path' => $request->url(), 'pageName' => 'page']);
        $filters = $request->safe()->only(['program_id', 'is_fixed', 'overlap_from', 'overlap_until']);
        if (array_key_exists('is_fixed', $filters)) {
            $filters['is_fixed'] = $command->query->isFixed;
        }

        return $this->success(data: $entries, message: 'Subscription placements retrieved successfully.', meta: ['filters' => (object) $filters], paginator: $paginator);
    }
}
