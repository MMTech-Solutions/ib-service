<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\ActivateModuleCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\ActivateModuleRequest;
use App\Features\Modules\Catalog\UseCases\ActivateModuleUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ActivateModuleController
{
    use ApiResponse;

    public function __invoke(
        ActivateModuleRequest $request,
        ActivateModuleUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $command = ActivateModuleCommand::fromRequest($request, $userContext);
        $module = $useCase->execute($command);

        return $this->success(
            data: $module->toArray(),
            message: 'Module activated successfully.',
        );
    }
}
