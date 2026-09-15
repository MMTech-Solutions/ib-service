<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\ShowRuleCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\ShowRuleRequest;
use App\Features\Rules\Catalog\UseCases\ShowRuleUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowRuleController
{
    use ApiResponse;

    public function __invoke(ShowRuleRequest $request, ShowRuleUseCase $useCase): JsonResponse
    {
        $rule = $useCase->execute(ShowRuleCommand::fromRequest($request));

        return $this->success(
            $rule->toArray(),
            'Rule retrieved successfully.',
        );
    }
}
