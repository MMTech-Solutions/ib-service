<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Ports\Output;

interface ProgressionFailurePort
{
    public function check(string $operation, string $stage, string $subscriptionId, string $resultId): void;
}
