<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\UpdateRuleVersionCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\UpdateRuleVersionRequest;
use App\Features\Rules\Catalog\UseCases\UpdateRuleVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateRuleVersionController
{
    use ApiResponse;

    public function __invoke(UpdateRuleVersionRequest $request, UpdateRuleVersionUseCase $useCase): JsonResponse
    {
        $version = $useCase->execute(UpdateRuleVersionCommand::fromRequest($request));

        return $this->success(
            $version->toArray(),
            'Rule version updated successfully.',
        );
    }
}
