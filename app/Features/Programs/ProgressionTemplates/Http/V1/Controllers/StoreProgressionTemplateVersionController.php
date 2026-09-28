<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\StoreProgressionTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreProgressionTemplateVersionController
{
    use ApiResponse;

    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, StoreProgressionTemplateVersionUseCase $useCase): JsonResponse
    {
        return $this->created($useCase->execute($progressionTemplate, ManageProgressionTemplateCommand::fromRequest($request))->toArray(), 'Progression template version created successfully.');
    }
}
