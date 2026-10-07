<?php

declare(strict_types=1);

return [
    'repository' => env('MODULES_REPOSITORY', 'postgresql'),
    'sources' => [
        'broker' => [
            'topic' => env('REWARDS_VOLUME_BROKER_TOPIC', 'broker-service'),
            'base_url' => rtrim((string) env('BROKER_CATALOG_BASE_URL', 'http://broker-app'), '/'),
            'internal_prefix' => '/api/broker/v1/internal',
            'internal_token' => env('BROKER_CATALOG_INTERNAL_TOKEN'),
            'source_service' => env('BROKER_CATALOG_SOURCE_SERVICE', 'mmt-ib-service'),
            'timeout_seconds' => (int) env('BROKER_CATALOG_TIMEOUT_SECONDS', 15),
        ],
        'copy_trading' => [
            'topic' => env('REWARDS_VOLUME_COPY_TRADING_TOPIC', ''),
            'base_url' => rtrim((string) env('COPY_TRADING_BASE_URL', ''), '/'),
            'internal_prefix' => '/api/copy-trading/v1/internal',
            'internal_token' => env('COPY_TRADING_INTERNAL_TOKEN'),
            'source_service' => env('COPY_TRADING_SOURCE_SERVICE', 'mmt-ib-service'),
            'timeout_seconds' => (int) env('COPY_TRADING_TIMEOUT_SECONDS', 15),
        ],
    ],

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
