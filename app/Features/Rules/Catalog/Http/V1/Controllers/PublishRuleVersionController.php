<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Controllers;

use App\Features\Rules\Catalog\Http\V1\Commands\PublishRuleVersionCommand;
use App\Features\Rules\Catalog\Http\V1\Requests\PublishRuleVersionRequest;
use App\Features\Rules\Catalog\UseCases\PublishRuleVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class PublishRuleVersionController
{
    use ApiResponse;

    public function __invoke(PublishRuleVersionRequest $request, PublishRuleVersionUseCase $useCase): JsonResponse
    {
        $version = $useCase->execute(PublishRuleVersionCommand::fromRequest($request));

        return $this->success(
            $version->toArray(),
            'Rule version published successfully.',
        );
    }
}
