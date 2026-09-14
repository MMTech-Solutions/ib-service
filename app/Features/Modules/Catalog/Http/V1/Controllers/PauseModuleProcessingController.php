<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\PauseModuleProcessingCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\PauseModuleProcessingRequest;
use App\Features\Modules\Catalog\UseCases\PauseModuleProcessingUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class PauseModuleProcessingController
{
    use ApiResponse;

    public function __invoke(
        PauseModuleProcessingRequest $request,
        PauseModuleProcessingUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $command = PauseModuleProcessingCommand::fromRequest($request, $userContext);
        $module = $useCase->execute($command);

        return $this->success(
            data: $module->toArray(),
            message: 'Module processing paused successfully.',
        );
    }
}
