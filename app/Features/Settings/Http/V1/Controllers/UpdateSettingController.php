<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Controllers;

use App\Features\Settings\Http\V1\Commands\UpdateSettingCommand;
use App\Features\Settings\Http\V1\Requests\UpdateSettingRequest;
use App\Features\Settings\UseCases\UpdateSettingUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateSettingController
{
    use ApiResponse;

    public function __invoke(UpdateSettingRequest $request, UpdateSettingUseCase $useCase, UserContext $context): JsonResponse
    {
        $result = $useCase->execute(UpdateSettingCommand::fromRequest($request, $context));

        return $this->success(data: $result->payload(), message: 'Setting updated.');
    }
}
