<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesResultData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Exceptions\UnsupportedProgressionActivityCapabilityException;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use Carbon\CarbonImmutable;

final class ListVolumeRewardActivitiesUseCase implements ListVolumeRewardActivitiesPort
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly BrokerInstrumentCatalogApiClient $broker,
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

        $page = $this->broker->progressionActivities([
            'from' => $from->toIso8601String(),
            'until' => $until->toIso8601String(),
            'instrument_references' => $query->instrument_references,
            'limit' => $limit,
            'cursor' => $query->cursor,
        ]);

        return new ListVolumeRewardActivitiesResultData(
            module_id: $module->id,
            module_condition: 'running',
            provider_invoked: true,
            activities: array_map(fn (array $item): VolumeRewardActivityData => $this->activity($module->id, $item), $page['data']),
            next_cursor: is_string($page['meta']['next_cursor'] ?? null) ? $page['meta']['next_cursor'] : null,
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

    /** @param array<string, mixed> $item */
    private function activity(string $moduleId, array $item): VolumeRewardActivityData
    {
        foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'symbol_id', 'server_group_id'] as $field) {
            if (! isset($item[$field]) || ! is_string($item[$field])) {
                throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid volume reward activity.');
            }
        }
        if ($item['metric_code'] !== 'closed_trading_volume') {
            throw InvalidProgressionActivityQueryException::withMessage('Broker returned an unsupported volume reward metric.');
        }

        $precision = $item['currency_precision'] ?? null;
        if ($precision !== null && ! is_int($precision)) {
            throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid server group currency precision.');
        }

        return new VolumeRewardActivityData(
            module_id: $moduleId,
            source_activity_id: $item['source_activity_id'],
            subject_external_user_id: $item['subject_external_user_id'],
            unit_code: $item['unit_code'],
            quantity: $item['quantity'],
            occurred_at: CarbonImmutable::parse($item['occurred_at'])->utc()->toIso8601String(),
            instrument_reference: 'broker:server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
            currency_code: isset($item['currency_code']) && is_string($item['currency_code']) ? strtoupper($item['currency_code']) : null,
            currency_precision: $precision,
            broker_granted_commission: isset($item['broker_granted_commission']) && is_string($item['broker_granted_commission']) ? $item['broker_granted_commission'] : null,
        );
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
