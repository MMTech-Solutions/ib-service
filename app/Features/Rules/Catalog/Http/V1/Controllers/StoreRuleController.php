<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\StoreRuleCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\StoreRuleRequest;
use App\Features\Rules\Catalog\UseCases\StoreRuleUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreRuleController
{
    use ApiResponse;

    public function __invoke(StoreRuleRequest $request, StoreRuleUseCase $useCase): JsonResponse
    {
        $rule = $useCase->execute(StoreRuleCommand::fromRequest($request));

        return $this->created(
            $rule->toArray(),
            'Rule created successfully.',
        );
    }
}
