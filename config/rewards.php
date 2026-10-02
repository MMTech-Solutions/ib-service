<?php

declare(strict_types=1);

return [
    'cpa' => [
        'batch_size' => (int) env('REWARDS_CPA_BATCH_SIZE', 100),
        'incremental_evidence' => filter_var(env('REWARDS_CPA_INCREMENTAL_EVIDENCE', false), FILTER_VALIDATE_BOOL),
    ],
    'settlement' => [
        'batch_size' => (int) env('REWARDS_SETTLEMENT_BATCH_SIZE', 100),
        'retry_delay_seconds' => (int) env('REWARDS_SETTLEMENT_RETRY_DELAY_SECONDS', 300),
        'claim_lease_seconds' => (int) env('REWARDS_SETTLEMENT_CLAIM_LEASE_SECONDS', 60),
    ],
    'auth_account_registered' => [
        'topic' => env('REWARDS_AUTH_ACCOUNT_REGISTERED_TOPIC', 'auth.events.v1'),
        'allowed_sources' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REWARDS_AUTH_ACCOUNT_REGISTERED_ALLOWED_SOURCES', '')),
        ))),
    ],
];
