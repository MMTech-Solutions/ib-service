<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\ListRulesCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\ListRulesRequest;
use App\Features\Rules\Catalog\UseCases\ListRulesUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListRulesController
{
    use ApiResponse;

    public function __invoke(ListRulesRequest $request, ListRulesUseCase $useCase): JsonResponse
    {
        $page = $useCase->execute(ListRulesCommand::fromRequest($request));

        return $this->success(
            array_map(
                static fn ($rule): array => $rule->toArray(),
                $page->rules,
            ),
            'Rules retrieved successfully.',
        );
    }
}
