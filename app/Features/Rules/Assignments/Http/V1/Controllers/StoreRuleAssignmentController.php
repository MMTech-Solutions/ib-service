<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\Http\V1\Commands\StoreRuleAssignmentCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\StoreRuleAssignmentRequest;
use App\Features\Rules\Assignments\UseCases\StoreRuleAssignmentUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreRuleAssignmentController
{
    use ApiResponse;

    public function __invoke(StoreRuleAssignmentRequest $request, StoreRuleAssignmentUseCase $useCase): JsonResponse
    {
        $assignment = $useCase->execute(StoreRuleAssignmentCommand::fromRequest($request));

        return $this->created(
            $assignment->toArray(),
            'Rule assignment created successfully.',
        );
    }
}
