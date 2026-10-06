<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\Http\V1\Commands\CpaConfigurationCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\CpaConfigurationRequest;
use App\Features\Rules\Assignments\UseCases\ManageCpaConfigurationUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CpaConfigurationController
{
    use ApiResponse;

    public function __invoke(CpaConfigurationRequest $request, ManageCpaConfigurationUseCase $useCase): JsonResponse|Response
    {
        $configuration = $useCase->execute(CpaConfigurationCommand::fromRequest($request));
        if ($request->isMethod('DELETE')) {
            return $this->noContent();
        }

        return $this->success($configuration?->toArray() ?? ['program_id' => $request->validated('program'), 'configured' => false], 'CPA configuration processed successfully.');
    }
}
