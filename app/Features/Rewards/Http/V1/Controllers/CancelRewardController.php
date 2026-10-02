<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Controllers;

use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\Http\V1\Requests\CancelRewardRequest;
use App\Features\Rewards\UseCases\ManageRewardFinancialOperationUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CancelRewardController
{
    use ApiResponse;

    public function __invoke(string $reward, CancelRewardRequest $request, UserContext $userContext, ManageRewardFinancialOperationUseCase $useCase): JsonResponse
    {
        $operation = $useCase->execute(new ManageRewardFinancialOperationData($reward, $userContext->id(), 'cancellation', (string) $request->validated('reason_code'), $request->validated('reason_label')));

        return $this->success($operation->toArray(), 'Reward cancellation processed successfully.');
    }
}
