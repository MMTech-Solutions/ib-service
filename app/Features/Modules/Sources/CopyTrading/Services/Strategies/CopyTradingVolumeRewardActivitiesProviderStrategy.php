<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services\Strategies;

use App\Features\Modules\Catalog\Contracts\Strategies\VolumeRewardActivitiesProviderStrategyInterface;
use App\Features\Modules\Catalog\DTOs\VolumeRewardActivitiesPageData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Sources\CopyTrading\Services\Adapters\CopyTradingVolumeRewardActivityNormalizer;
use App\Features\Modules\Sources\CopyTrading\Services\CopyTradingApiClient;

final class CopyTradingVolumeRewardActivitiesProviderStrategy implements VolumeRewardActivitiesProviderStrategyInterface
{
    public function __construct(private readonly CopyTradingApiClient $client, private readonly CopyTradingVolumeRewardActivityNormalizer $normalizer) {}

    public function fetch(ListVolumeRewardActivitiesQueryData $query): VolumeRewardActivitiesPageData
    {
        $page = $this->client->get('/progression-activities', [
            'from' => $query->occurred_from, 'until' => $query->occurred_until,
            'instrument_references' => $query->instrument_references, 'limit' => $query->limit, 'cursor' => $query->cursor,
        ]);
        if (! array_key_exists('next_cursor', $page['meta']) || ($page['meta']['next_cursor'] !== null && ! is_string($page['meta']['next_cursor']))) {
            throw InvalidVolumeRewardActivityException::create();
        }

        return new VolumeRewardActivitiesPageData(array_map(fn (array $item) => $this->normalizer->normalize($query->module_id, $item), $page['data']), $page['meta']['next_cursor']);
    }
}
