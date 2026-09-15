<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\UpdateRuleCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\UpdateRuleRequest;
use App\Features\Rules\Catalog\UseCases\UpdateRuleUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateRuleController
{
    use ApiResponse;

    public function __invoke(UpdateRuleRequest $request, UpdateRuleUseCase $useCase): JsonResponse
    {
        $rule = $useCase->execute(UpdateRuleCommand::fromRequest($request));

        return $this->success(
            $rule->toArray(),
            'Rule updated successfully.',
        );
    }
}
