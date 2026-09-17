<?php

declare(strict_types=1);

namespace App\Features\Progression\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesQueryData;
use App\Features\Modules\Contracts\Ports\Input\ListProgressionActivitiesPort;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesResultData;
use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;

/**
 * Progression outbound adapter: depends only on Modules public contracts.
 */
final class ModulesFetchProgressionActivitiesAdapter implements FetchProgressionActivitiesPort
{
    public function __construct(
        private readonly ListProgressionActivitiesPort $listProgressionActivities,
    ) {}

    public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
    {
        $result = $this->listProgressionActivities->list(new ListProgressionActivitiesQueryData(
            module_id: $query->module_id,
            occurred_from: $query->occurred_from,
            occurred_until: $query->occurred_until,
            cursor: $query->cursor,
            limit: $query->limit,
        ));

        $activities = array_map(
            static fn ($activity): NormalizedActivityData => new NormalizedActivityData(
                module_id: $activity->module_id,
                source_activity_id: $activity->source_activity_id,
                subject_external_user_id: $activity->subject_external_user_id,
                metric_code: $activity->metric_code,
                unit_code: $activity->unit_code,
                quantity: $activity->quantity,
                occurred_at: $activity->occurred_at,
                instrument_reference: $activity->instrument_reference,
            ),
            $result->activities,
        );

        return new FetchProgressionActivitiesResultData(
            module_id: $result->module_id,
            module_condition: $result->module_condition,
            provider_invoked: $result->provider_invoked,
            activities: $activities,
            next_cursor: $result->next_cursor,
            rejection_code: $result->rejection_code,
        );
    }
}
