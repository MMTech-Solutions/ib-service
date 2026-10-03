<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Controllers;

use App\Features\Rewards\Actions\ResolveRewardReadAccessAction;
use App\Features\Rewards\DTOs\RewardReadData;
use App\Features\Rewards\DTOs\RewardReadPageData;
use App\Features\Rewards\Http\V1\Commands\ReadRewardsCommand;
use App\Features\Rewards\Http\V1\Requests\ReadRewardsRequest;
use App\Features\Rewards\Http\V1\Resources\RewardReadResource;
use App\Features\Rewards\UseCases\ReadRewardsUseCase;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;
use Spatie\LaravelData\Data;

final class ReadRewardsController
{
    use ApiResponse;

    public function __invoke(ReadRewardsRequest $request, UserContext $user, ResolveRewardReadAccessAction $access, ReadRewardsUseCase $useCase): JsonResponse
    {
        $scope = $access->resolve($user, UserSurface::from((string) $request->route('user_surface')));
        $command = ReadRewardsCommand::fromRequest($request, $scope);
        $result = $useCase->execute($command->query);
        if ($result instanceof RewardReadPageData) {
            $items = array_map(fn (Data $item) => $this->serialize($item), $result->items);
            $paginator = new LengthAwarePaginator($items, $result->total, $result->per_page, $result->page, ['path' => $request->url()]);

            return $this->success($items, 'Rewards records retrieved successfully.', meta: ['filters' => (object) $command->query->filters], paginator: $paginator);
        }

        return $this->success($this->serialize($result), 'Reward record retrieved successfully.');
    }

    /** @return array<string, mixed> */
    private function serialize(Data $item): array
    {
        return $item instanceof RewardReadData ? (new RewardReadResource($item))->toArray() : $item->toArray();
    }
}
