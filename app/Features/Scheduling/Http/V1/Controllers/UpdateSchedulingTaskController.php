<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Controllers;

use App\Features\Scheduling\Http\V1\Commands\UpdateSchedulingTaskCommand;
use App\Features\Scheduling\Http\V1\Requests\UpdateSchedulingTaskRequest;
use App\Features\Scheduling\UseCases\UpdateSchedulingTaskUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateSchedulingTaskController
{
    use ApiResponse;

    public function __invoke(UpdateSchedulingTaskRequest $request, UpdateSchedulingTaskUseCase $useCase, UserContext $context): JsonResponse
    {
        $result = $useCase->execute(UpdateSchedulingTaskCommand::fromRequest($request, $context)->input);

        return $this->success($result->toArray(), 'Scheduling configuration updated.');
    }
}
