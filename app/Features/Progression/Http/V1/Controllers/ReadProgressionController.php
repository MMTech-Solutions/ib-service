<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Controllers;

use App\Features\Progression\Http\V1\Commands\ReadProgressionCommand;
use App\Features\Progression\Http\V1\Requests\ReadProgressionRequest;
use App\Features\Progression\UseCases\ReadProgressionUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReadProgressionController
{
    use ApiResponse;

    public function __invoke(ReadProgressionRequest $request, ReadProgressionUseCase $useCase): JsonResponse
    {
        $command = ReadProgressionCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $items = array_map(static fn ($item): array => $item->toArray(), $result->items);
        if ($command->query->id !== null) {
            return $this->success(data: $items[0], message: 'Progression artifact retrieved.');
        }

        return $this->success(data: $items, message: 'Progression artifacts retrieved.', meta: ['filters' => (object) $command->query->filters],
            paginator: new LengthAwarePaginator($items, $result->total, $command->query->perPage, $command->query->page, ['path' => $request->url()]));
    }
}
