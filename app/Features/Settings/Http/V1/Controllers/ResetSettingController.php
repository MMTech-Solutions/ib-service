<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Controllers;

use App\Features\Settings\Http\V1\Commands\ResetSettingCommand;
use App\Features\Settings\Http\V1\Requests\ResetSettingRequest;
use App\Features\Settings\UseCases\ResetSettingUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ResetSettingController
{
    use ApiResponse;

    public function __invoke(ResetSettingRequest $request, ResetSettingUseCase $useCase, UserContext $context): JsonResponse
    {
        $result = $useCase->execute(ResetSettingCommand::fromRequest($request, $context));

        return $this->success(data: $result->payload(), message: 'Setting reset.');
    }
}
