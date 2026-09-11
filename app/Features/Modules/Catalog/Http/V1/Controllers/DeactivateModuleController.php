<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\DeactivateModuleCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\DeactivateModuleRequest;
use App\Features\Modules\Catalog\UseCases\DeactivateModuleUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class DeactivateModuleController
{
    use ApiResponse;

    public function __invoke(
        DeactivateModuleRequest $request,
        DeactivateModuleUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $command = DeactivateModuleCommand::fromRequest($request, $userContext);
        $module = $useCase->execute($command);

        return $this->success(
            data: ['module' => $module->toArray()],
            message: 'Module deactivated successfully.',
        );
    }
}
