<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Controllers;

use App\Features\Rewards\Actions\ResolveCpaProgressReadAccessAction;
use App\Features\Rewards\DTOs\CpaVerificationProgressData;
use App\Features\Rewards\Http\V1\Commands\ListCpaVerificationProgressCommand;
use App\Features\Rewards\Http\V1\Requests\ListCpaVerificationProgressRequest;
use App\Features\Rewards\Http\V1\Resources\CpaVerificationProgressResource;
use App\Features\Rewards\UseCases\ListCpaVerificationProgressUseCase;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListCpaVerificationProgressController
{
    use ApiResponse;

    public function __invoke(
        ListCpaVerificationProgressRequest $request,
        UserContext $userContext,
        ResolveCpaProgressReadAccessAction $accessAction,
        ListCpaVerificationProgressUseCase $useCase,
    ): JsonResponse {
        $access = $accessAction->resolve($userContext, UserSurface::from((string) $request->route('user_surface')));
        $command = ListCpaVerificationProgressCommand::fromRequest($request, $access);
        $result = $useCase->execute($command->toQueryData());
        $items = array_map(
            static fn (CpaVerificationProgressData $progress): array => (new CpaVerificationProgressResource($progress, $access->is_administrative))->toArray(),
            $result->items,
        );
        $paginator = new LengthAwarePaginator(
            items: $items,
            total: $result->total,
            perPage: $result->per_page,
            currentPage: $result->current_page,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter([
            'ib_user_id' => $command->ib_user_id,
            'referred_user_id' => $command->referred_user_id,
            'program_id' => $command->program_id,
            'module_id' => $command->module_id,
            'status' => $command->status,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return $this->success(
            data: $items,
            message: 'CPA verification progress retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
