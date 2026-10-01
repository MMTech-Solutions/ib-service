<?php

declare(strict_types=1);

return [
    'base_url' => rtrim((string) env('FINANCE_API_URL', 'http://finance-app'), '/'),
    'internal_token' => env('FINANCE_INTERNAL_TOKEN'),
    'source_service' => env('FINANCE_SOURCE_SERVICE', 'mmt-ib-service'),
    'timeout_seconds' => (int) env('FINANCE_TIMEOUT_SECONDS', 5),
];
