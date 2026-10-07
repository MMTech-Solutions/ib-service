<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Controllers;

use App\Features\Scheduling\Http\V1\Commands\RequestSchedulingRunCommand;
use App\Features\Scheduling\Http\V1\Requests\RequestSchedulingRunRequest;
use App\Features\Scheduling\Services\PresentSchedulingData;
use App\Features\Scheduling\UseCases\RequestSchedulingRunUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class RequestSchedulingRunController
{
    use ApiResponse;

    public function __invoke(RequestSchedulingRunRequest $request, RequestSchedulingRunUseCase $useCase, UserContext $context, PresentSchedulingData $present): JsonResponse
    {
        $result = $useCase->execute(RequestSchedulingRunCommand::fromRequest($request, $context)->input);

        return $this->accepted($present->run($result), 'Scheduling execution accepted.');
    }
}
