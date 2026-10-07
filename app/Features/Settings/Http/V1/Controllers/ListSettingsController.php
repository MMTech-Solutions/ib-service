<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Controllers;

use App\Features\Settings\DTOs\SettingViewData;
use App\Features\Settings\Http\V1\Commands\ListSettingsCommand;
use App\Features\Settings\Http\V1\Requests\ListSettingsRequest;
use App\Features\Settings\UseCases\ListSettingsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListSettingsController
{
    use ApiResponse;

    public function __invoke(ListSettingsRequest $request, ListSettingsUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ListSettingsCommand::fromRequest($request));
        $items = array_map(static fn (SettingViewData $item): array => $item->payload(), $result->items);
        $paginator = new LengthAwarePaginator($items, $result->total, $result->per_page, $result->page, ['path' => $request->url()]);

        return $this->success(data: $items, message: 'Settings retrieved.', meta: ['filters' => (object) $request->safe()->only(['search', 'domain', 'section', 'provider'])], paginator: $paginator);
    }
}
