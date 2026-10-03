<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Factories\VolumeRewardActivitiesProviderFactory;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesResultData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Exceptions\UnsupportedProgressionActivityCapabilityException;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use Carbon\CarbonImmutable;

final class ListVolumeRewardActivitiesUseCase implements ListVolumeRewardActivitiesPort
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly VolumeRewardActivitiesProviderFactory $providers,
    ) {}

    public function execute(ListVolumeRewardActivitiesQueryData $query): ListVolumeRewardActivitiesResultData
    {
        $module = $this->repositoryFactory->make()->findById($query->module_id);
        if ($module === null) {
            throw ModuleNotFoundException::forIds([$query->module_id]);
        }

        [$from, $until, $limit] = $this->validatedBounds($query);
        if (! $module->isActive || $module->processingStatus->value !== 'running') {
            return new ListVolumeRewardActivitiesResultData(
                module_id: $module->id,
                module_condition: $module->isActive ? $module->processingStatus->value : 'inactive',
                provider_invoked: false,
                activities: [],
                next_cursor: null,
                rejection_code: $module->isActive ? 'module_paused' : 'module_inactive',
            );
        }
        if ($module->code !== 'broker') {
            throw InvalidProgressionActivityQueryException::withMessage('Volume reward activity is unavailable for this module.');
        }
        if (! $this->hasClosedTradingVolumeCapability($module->capabilities)) {
            throw UnsupportedProgressionActivityCapabilityException::forModule($module->id);
        }

        $page = $this->providers->make($module->code)->fetch(new ListVolumeRewardActivitiesQueryData(
            $query->module_id, $from->toIso8601String(), $until->toIso8601String(),
            $query->instrument_references, $query->cursor, $limit,
        ));

        return new ListVolumeRewardActivitiesResultData(
            module_id: $module->id,
            module_condition: 'running',
            provider_invoked: true,
            activities: $page->activities,
            next_cursor: $page->next_cursor,
            rejection_code: null,
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int} */
    private function validatedBounds(ListVolumeRewardActivitiesQueryData $query): array
    {
        try {
            $from = CarbonImmutable::parse($query->occurred_from)->utc();
            $until = CarbonImmutable::parse($query->occurred_until)->utc();
        } catch (\Throwable) {
            throw InvalidProgressionActivityQueryException::withMessage('Volume reward activity requires valid UTC timestamps.');
        }
        if (! $from->lt($until) || $query->instrument_references === []) {
            throw InvalidProgressionActivityQueryException::withMessage('Volume reward activity requires a valid interval and configured instruments.');
        }

        $limit = $query->limit ?? (int) config('modules.activity.default_limit', 100);
        if ($limit < 1 || $limit > (int) config('modules.activity.max_limit', 200)) {
            throw InvalidProgressionActivityQueryException::withMessage('Volume reward activity page limit is invalid.');
        }

        return [$from, $until, $limit];
    }

    /** @param list<object> $capabilities */
    private function hasClosedTradingVolumeCapability(array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if ($capability->code === 'closed_trading_volume' && $capability->isActive) {
                return true;
            }
        }

        return false;
    }
}
