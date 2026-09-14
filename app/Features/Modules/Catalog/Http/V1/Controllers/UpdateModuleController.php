<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\UpdateModuleCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\UpdateModuleRequest;
use App\Features\Modules\Catalog\UseCases\UpdateModuleUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateModuleController
{
    use ApiResponse;

    public function __invoke(UpdateModuleRequest $request, UpdateModuleUseCase $useCase): JsonResponse
    {
        $command = UpdateModuleCommand::fromRequest($request);
        $module = $useCase->execute($command);

        return $this->success(
            data: $module->toArray(),
            message: 'Module updated successfully.',
        );
    }
}
