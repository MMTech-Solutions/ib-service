<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class TaskDefinitionData extends Data
{
    public function __construct(public readonly string $code, public readonly string $description, public readonly string $cron_expression, public readonly string $command, /** @var array<string, bool|string|int> */ public readonly array $arguments = [], public readonly int $timeout_seconds = 3600) {}
}
