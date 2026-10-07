<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SettingAuditData extends Data
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function __construct(
        public readonly string $key,
        public readonly string $actor,
        public readonly string $action,
        public readonly string $reason,
        public readonly ?array $before,
        public readonly ?array $after,
        public readonly string $occurred_at,
    ) {}
}
