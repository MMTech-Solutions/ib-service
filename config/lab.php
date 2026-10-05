<?php

return [
    'profile' => env('IB_LAB_PROFILE', false),
    'clock_mode' => env('IB_DOMAIN_CLOCK', 'system'),
    'clock_file' => env('IB_LAB_CLOCK_FILE'),
    'failures_enabled' => env('IB_LAB_FAILURES_ENABLED', false),
];
