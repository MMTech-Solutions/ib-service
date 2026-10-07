<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Controllers;

use App\Features\Scheduling\Http\V1\Commands\ReadSchedulingCommand;
use App\Features\Scheduling\Http\V1\Requests\ReadSchedulingRequest;
use App\Features\Scheduling\UseCases\ReadSchedulingUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReadSchedulingController
{
    use ApiResponse;

    public function __invoke(ReadSchedulingRequest $request, ReadSchedulingUseCase $useCase): JsonResponse
    {
        $command = ReadSchedulingCommand::fromRequest($request);
        $result = $useCase->execute($command->query);
        $paginator = $result->total !== null ? new LengthAwarePaginator($result->payload, $result->total, $command->query->per_page, $command->query->page, ['path' => $request->url()]) : null;

        return $this->success($result->payload, 'Scheduling retrieved successfully.', ['filters' => (object) $request->safe()->only(['code', 'status', 'origin', 'from', 'until'])], paginator: $paginator);
    }
}
