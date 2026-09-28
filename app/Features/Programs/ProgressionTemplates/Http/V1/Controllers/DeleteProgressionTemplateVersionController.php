<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\DeleteProgressionTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;

final class DeleteProgressionTemplateVersionController
{
    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, string $version, DeleteProgressionTemplateVersionUseCase $useCase): JsonResponse
    {
        $useCase->execute($progressionTemplate, $version, (int) $request->validated('lock_version'));

        return response()->json(null, 204);
    }
}
