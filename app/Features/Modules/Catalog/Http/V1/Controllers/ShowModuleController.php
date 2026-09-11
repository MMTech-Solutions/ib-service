<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\ShowModuleCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\ShowModuleRequest;
use App\Features\Modules\Catalog\UseCases\ShowModuleUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowModuleController
{
    use ApiResponse;

    public function __invoke(ShowModuleRequest $request, ShowModuleUseCase $useCase): JsonResponse
    {
        $command = ShowModuleCommand::fromRequest($request);
        $module = $useCase->execute($command);

        return $this->success(
            data: ['module' => $module->toArray()],
            message: 'Module retrieved successfully.',
        );
    }
}
