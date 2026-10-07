<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Controllers;

use App\Features\Settings\Http\V1\Commands\CertifySettingsConnectionCommand;
use App\Features\Settings\Http\V1\Requests\CertifySettingsConnectionRequest;
use App\Features\Settings\UseCases\CertifySettingsConnectionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class CertifySettingsConnectionController
{
    use ApiResponse;

    public function __invoke(CertifySettingsConnectionRequest $request, CertifySettingsConnectionUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(CertifySettingsConnectionCommand::fromRequest($request));

        return $this->success(data: $result->toArray(), message: 'Connection certification completed.');
    }
}
