<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\ListRuleVersionsCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\ListRuleVersionsRequest;
use App\Features\Rules\Catalog\UseCases\ListRuleVersionsUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListRuleVersionsController
{
    use ApiResponse;

    public function __invoke(ListRuleVersionsRequest $request, ListRuleVersionsUseCase $useCase): JsonResponse
    {
        $page = $useCase->execute(ListRuleVersionsCommand::fromRequest($request));

        return $this->success(
            array_map(
                static fn ($version): array => $version->toArray(),
                $page->versions,
            ),
            'Rule versions retrieved successfully.',
        );
    }
}
