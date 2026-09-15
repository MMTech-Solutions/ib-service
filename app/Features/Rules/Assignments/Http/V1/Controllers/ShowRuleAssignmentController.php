<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\Http\V1\Commands\ShowRuleAssignmentCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\ShowRuleAssignmentRequest;
use App\Features\Rules\Assignments\UseCases\ShowRuleAssignmentUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowRuleAssignmentController
{
    use ApiResponse;

    public function __invoke(ShowRuleAssignmentRequest $request, ShowRuleAssignmentUseCase $useCase): JsonResponse
    {
        $assignment = $useCase->execute(ShowRuleAssignmentCommand::fromRequest($request));

        return $this->success(
            $assignment->toArray(),
            'Rule assignment retrieved successfully.',
        );
    }
}
