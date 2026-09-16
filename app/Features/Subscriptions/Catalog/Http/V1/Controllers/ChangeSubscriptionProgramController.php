<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Controllers;

use App\Features\Subscriptions\Catalog\Http\V1\Commands\ChangeSubscriptionProgramCommand;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ChangeSubscriptionProgramRequest;
use App\Features\Subscriptions\Catalog\UseCases\ChangeSubscriptionProgramUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ChangeSubscriptionProgramController
{
    use ApiResponse;

    public function __invoke(
        ChangeSubscriptionProgramRequest $request,
        ChangeSubscriptionProgramUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $subscription = $useCase->execute(ChangeSubscriptionProgramCommand::fromRequest($request, $userContext));

        return $this->success(
            $subscription->toArray(),
            'Subscription program changed successfully.',
        );
    }
}
