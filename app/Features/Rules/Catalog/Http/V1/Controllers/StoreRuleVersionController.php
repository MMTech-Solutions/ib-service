<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\StoreRuleVersionCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\StoreRuleVersionRequest;
use App\Features\Rules\Catalog\UseCases\StoreRuleVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreRuleVersionController
{
    use ApiResponse;

    public function __invoke(StoreRuleVersionRequest $request, StoreRuleVersionUseCase $useCase): JsonResponse
    {
        $version = $useCase->execute(StoreRuleVersionCommand::fromRequest($request));

        return $this->created(
            $version->toArray(),
            'Rule version created successfully.',
        );
    }
}
