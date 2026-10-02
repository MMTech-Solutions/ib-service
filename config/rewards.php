<?php

declare(strict_types=1);

return [
    'repository' => env('REWARDS_REPOSITORY', 'postgresql'),
    'minimum_amount_major' => env('REWARDS_MINIMUM_AMOUNT_MAJOR', '0.01'),
    'volume' => [
        'trading_topic' => env('REWARDS_VOLUME_TRADING_TOPIC', 'trading-services.events.v1'),
        'broker_module_id' => env('REWARDS_VOLUME_BROKER_MODULE_ID'),
        'event_retry_delay_seconds' => (int) env('REWARDS_VOLUME_EVENT_RETRY_DELAY_SECONDS', 60),
    ],
    'cpa' => [
        'batch_size' => (int) env('REWARDS_CPA_BATCH_SIZE', 100),
        'incremental_evidence' => filter_var(env('REWARDS_CPA_INCREMENTAL_EVIDENCE', false), FILTER_VALIDATE_BOOL),
    ],
    'settlement' => [
        'batch_size' => (int) env('REWARDS_SETTLEMENT_BATCH_SIZE', 100),
        'retry_delay_seconds' => (int) env('REWARDS_SETTLEMENT_RETRY_DELAY_SECONDS', 300),
        'claim_lease_seconds' => (int) env('REWARDS_SETTLEMENT_CLAIM_LEASE_SECONDS', 60),
    ],
    'reconciliation' => [
        'batch_size' => (int) env('REWARDS_RECONCILIATION_BATCH_SIZE', 100),
    ],
    'auth_account_registered' => [
        'topic' => env('REWARDS_AUTH_ACCOUNT_REGISTERED_TOPIC', 'auth.events.v1'),
        'allowed_sources' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REWARDS_AUTH_ACCOUNT_REGISTERED_ALLOWED_SOURCES', '')),
        ))),
    ],
];
