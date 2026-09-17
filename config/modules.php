<?php

declare(strict_types=1);

return [
    'repository' => env('MODULES_REPOSITORY', 'postgresql'),

    /*
    | Technical bounds for M3 pull-only activity queries. These are transport
    | safeguards, not progression business rules.
    */
    'activity' => [
        'default_limit' => (int) env('MODULES_ACTIVITY_DEFAULT_LIMIT', 100),
        'max_limit' => (int) env('MODULES_ACTIVITY_MAX_LIMIT', 200),
        'max_range_days' => (int) env('MODULES_ACTIVITY_MAX_RANGE_DAYS', 31),
        'timeout_seconds' => (int) env('MODULES_ACTIVITY_TIMEOUT_SECONDS', 5),
    ],
];
