<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\Http\V1\Commands\WithdrawRuleAssignmentCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\WithdrawRuleAssignmentRequest;
use App\Features\Rules\Assignments\UseCases\WithdrawRuleAssignmentUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class WithdrawRuleAssignmentController
{
    use ApiResponse;

    public function __invoke(WithdrawRuleAssignmentRequest $request, WithdrawRuleAssignmentUseCase $useCase): JsonResponse
    {
        $assignment = $useCase->execute(WithdrawRuleAssignmentCommand::fromRequest($request));

        return $this->success(
            $assignment->toArray(),
            'Rule assignment withdrawn successfully.',
        );
    }
}
