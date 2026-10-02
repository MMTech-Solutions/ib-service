<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Controllers;

use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\Http\V1\Requests\CreateRewardCompensationRequest;
use App\Features\Rewards\UseCases\ManageRewardFinancialOperationUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CreateRewardCompensationController
{
    use ApiResponse;

    public function __invoke(string $reward, CreateRewardCompensationRequest $request, UserContext $userContext, ManageRewardFinancialOperationUseCase $useCase): JsonResponse
    {
        $operation = $useCase->execute(new ManageRewardFinancialOperationData($reward, $userContext->id(), 'compensation', (string) $request->validated('reason_code'), $request->validated('reason_label'), (int) $request->validated('amount_minor'), (string) $request->validated('idempotency_key')));

        return $this->success($operation->toArray(), 'Reward compensation processed successfully.');
    }
}
