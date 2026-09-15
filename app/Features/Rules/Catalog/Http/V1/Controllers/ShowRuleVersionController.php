<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\ShowRuleVersionCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\ShowRuleVersionRequest;
use App\Features\Rules\Catalog\UseCases\ShowRuleVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowRuleVersionController
{
    use ApiResponse;

    public function __invoke(ShowRuleVersionRequest $request, ShowRuleVersionUseCase $useCase): JsonResponse
    {
        $version = $useCase->execute(ShowRuleVersionCommand::fromRequest($request));

        return $this->success(
            $version->toArray(),
            'Rule version retrieved successfully.',
        );
    }
}
