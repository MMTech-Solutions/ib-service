<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\CertifyModuleConnectionCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\CertifyModuleConnectionRequest;
use App\Features\Modules\Catalog\UseCases\CertifyModuleConnectionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CertifyModuleConnectionController
{
    use ApiResponse;

    public function __invoke(CertifyModuleConnectionRequest $request, CertifyModuleConnectionUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(CertifyModuleConnectionCommand::fromRequest($request));

        return $this->success(data: $result->toArray(), message: 'Connection certification completed.');
    }
}
