<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\DeleteProgressionTemplateUseCase;
use Illuminate\Http\JsonResponse;

final class DeleteProgressionTemplateController
{
    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, DeleteProgressionTemplateUseCase $useCase): JsonResponse
    {
        $useCase->execute($progressionTemplate, (int) $request->validated('lock_version'));

        return response()->json(null, 204);
    }
}
