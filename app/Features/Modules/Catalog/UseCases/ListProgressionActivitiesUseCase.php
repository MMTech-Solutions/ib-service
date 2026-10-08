<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleActivitySourceFactory;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Services\ModuleActivityRejectionEvidence;
use App\Features\Modules\Catalog\Support\ProgressionActivityCursor;
use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesResultData;
use App\Features\Modules\Contracts\Data\V1\ProgressionActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Exceptions\UnsupportedProgressionActivityCapabilityException;
use App\Features\Modules\Contracts\Ports\Input\ListProgressionActivitiesPort;
use App\Features\Modules\Sources\Contracts\ModuleActivitySourceInterface;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Carbon\CarbonImmutable;

final class ListProgressionActivitiesUseCase implements ListProgressionActivitiesPort
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly ModuleActivitySourceFactory $activitySourceFactory,
        private readonly ModuleActivityRejectionEvidence $rejectionEvidence,
    ) {}

    public function list(ListProgressionActivitiesQueryData $query): ListProgressionActivitiesResultData
    {
        $module = $this->repositoryFactory->make()->findById($query->module_id);
        if ($module === null) {
            throw ModuleNotFoundException::forIds([$query->module_id]);
        }

        [$occurredFrom, $occurredUntil, $limit] = $this->validateQueryBounds($query);

        if (! $module->isActive) {
            $this->rejectionEvidence->recordInactiveModuleRejection($module->id, [
                'occurred_from' => $occurredFrom->toIso8601String(),
                'occurred_until' => $occurredUntil->toIso8601String(),
            ]);

            return new ListProgressionActivitiesResultData(
                module_id: $module->id,
                module_condition: 'inactive',
                provider_invoked: false,
                activities: [],
                next_cursor: null,
                rejection_code: 'module_inactive',
            );
        }

        $sources = $this->resolveSources($module);
        if ($sources === []) {
            throw UnsupportedProgressionActivityCapabilityException::forModule($module->id);
        }

        [$afterOccurredAt, $afterSourceActivityId] = $query->cursor === null
            ? [null, null]
            : ProgressionActivityCursor::decode($query->cursor);

        $merged = [];
        foreach ($sources as $source) {
            $merged = array_merge(
                $merged,
                $source->fetchPage(
                    moduleId: $module->id,
                    occurredFrom: $occurredFrom,
                    occurredUntil: $occurredUntil,
                    afterOccurredAt: $afterOccurredAt,
                    afterSourceActivityId: $afterSourceActivityId,
                    limit: $limit + 1,
                ),
            );
        }

        usort(
            $merged,
            static function (ProgressionActivityData $left, ProgressionActivityData $right): int {
                $byTime = strcmp($left->occurred_at, $right->occurred_at);
                if ($byTime !== 0) {
                    return $byTime;
                }

                return strcmp($left->source_activity_id, $right->source_activity_id);
            },
        );

        $hasMore = count($merged) > $limit;
        $page = array_slice($merged, 0, $limit);
        $nextCursor = null;
        if ($hasMore && $page !== []) {
            $last = $page[array_key_last($page)];
            $nextCursor = ProgressionActivityCursor::encode($last->occurred_at, $last->source_activity_id);
        }

        return new ListProgressionActivitiesResultData(
            module_id: $module->id,
            module_condition: $module->processingStatus->value,
            provider_invoked: true,
            activities: array_values($page),
            next_cursor: $nextCursor,
            rejection_code: null,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function validateQueryBounds(ListProgressionActivitiesQueryData $query): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['modules.activity.max_range_days', 'modules.activity.default_limit', 'modules.activity.max_limit']);
        try {
            $occurredFrom = CarbonImmutable::parse($query->occurred_from)->utc();
            $occurredUntil = CarbonImmutable::parse($query->occurred_until)->utc();
        } catch (\Throwable) {
            throw InvalidProgressionActivityQueryException::withMessage(
                'occurred_from and occurred_until must be valid UTC timestamps.',
            );
        }

        if (! $occurredFrom->lt($occurredUntil)) {
            throw InvalidProgressionActivityQueryException::withMessage(
                'occurred_from must be strictly before occurred_until.',
            );
        }

        $maxRangeDays = (int) $settings->get('modules.activity.max_range_days');
        if ($occurredFrom->diffInDays($occurredUntil) > $maxRangeDays) {
            throw InvalidProgressionActivityQueryException::withMessage(
                "The activity query range may not exceed {$maxRangeDays} days.",
            );
        }

        $defaultLimit = (int) $settings->get('modules.activity.default_limit');
        $maxLimit = (int) $settings->get('modules.activity.max_limit');
        $limit = $query->limit ?? $defaultLimit;
        if ($limit < 1 || $limit > $maxLimit) {
            throw InvalidProgressionActivityQueryException::withMessage(
                "Activity page limit must be between 1 and {$maxLimit}.",
            );
        }

        return [$occurredFrom, $occurredUntil, $limit];
    }

    /**
     * @return list<ModuleActivitySourceInterface>
     */
    private function resolveSources(Module $module): array
    {
        $sources = [];
        foreach ($module->capabilities as $capability) {
            if (! $capability->isActive) {
                continue;
            }
            if (! $this->activitySourceFactory->supports($capability->code)) {
                continue;
            }
            $sources[] = $this->activitySourceFactory->makeForCapability($capability->code, $module->code);
        }

        return $sources;
    }
}
