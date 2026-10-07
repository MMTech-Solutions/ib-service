<?php

return [
    'repository' => 'postgresql',
    'timeout_seconds' => (int) env('SCHEDULING_TIMEOUT_SECONDS', 3600),
    'task_timeouts' => [],
    'output_limit_bytes' => 1048576,
    'heartbeat_seconds' => 15,
    'stale_seconds' => 120,
];
