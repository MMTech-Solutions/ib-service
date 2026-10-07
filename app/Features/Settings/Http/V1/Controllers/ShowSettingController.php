<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Controllers;

use App\Features\Settings\Http\V1\Commands\ShowSettingCommand;
use App\Features\Settings\Http\V1\Requests\ShowSettingRequest;
use App\Features\Settings\UseCases\ShowSettingUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowSettingController
{
    use ApiResponse;

    public function __invoke(ShowSettingRequest $request, ShowSettingUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ShowSettingCommand::fromRequest($request));

        return $this->success(data: $result->payload(), message: 'Setting retrieved.');
    }
}
