<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\Http\V1\Commands\ReplaceRuleAssignmentCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\ReplaceRuleAssignmentRequest;
use App\Features\Rules\Assignments\UseCases\ReplaceRuleAssignmentUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReplaceRuleAssignmentController
{
    use ApiResponse;

    public function __invoke(ReplaceRuleAssignmentRequest $request, ReplaceRuleAssignmentUseCase $useCase): JsonResponse
    {
        $assignment = $useCase->execute(ReplaceRuleAssignmentCommand::fromRequest($request));

        return $this->success(
            $assignment->toArray(),
            'Rule assignment replaced successfully.',
        );
    }
}
