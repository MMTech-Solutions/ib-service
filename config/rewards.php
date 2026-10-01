<?php

declare(strict_types=1);

return [
    'auth_account_registered' => [
        'topic' => env('REWARDS_AUTH_ACCOUNT_REGISTERED_TOPIC', 'auth.events.v1'),
        'allowed_sources' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REWARDS_AUTH_ACCOUNT_REGISTERED_ALLOWED_SOURCES', '')),
        ))),
    ],
];
