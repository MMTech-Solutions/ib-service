<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ReplaceProgramVolumeRewardConfigurationCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ReplaceProgramVolumeRewardConfigurationRequest;
use App\Features\Programs\Catalog\UseCases\ReplaceProgramVolumeRewardConfigurationUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReplaceProgramVolumeRewardConfigurationController
{
    use ApiResponse;

    public function __invoke(ReplaceProgramVolumeRewardConfigurationRequest $request, ReplaceProgramVolumeRewardConfigurationUseCase $useCase): JsonResponse
    {
        return $this->success(
            $useCase->execute(ReplaceProgramVolumeRewardConfigurationCommand::fromRequest($request)),
            'Program volume reward configuration replaced successfully.',
        );
    }
}
