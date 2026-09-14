<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\ArchivePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ArchivePlanRequest;
use App\Features\Plans\Catalog\UseCases\ArchivePlanUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\Response;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ArchivePlanController
{
    use ApiResponse;

    public function __invoke(
        ArchivePlanRequest $request,
        ArchivePlanUseCase $useCase,
        UserContext $userContext,
    ): Response {
        $useCase->execute(ArchivePlanCommand::fromRequest($request, $userContext));

        return $this->noContent();
    }
}
