<?php

return [
    'base_url' => rtrim((string) env('BROKER_CATALOG_BASE_URL', 'http://broker-app'), '/'),
    'internal_token' => env('BROKER_CATALOG_INTERNAL_TOKEN'),
    'source_service' => env('BROKER_CATALOG_SOURCE_SERVICE', 'mmt-ib-service'),
    'timeout_seconds' => (int) env('BROKER_CATALOG_TIMEOUT_SECONDS', 15),
];
