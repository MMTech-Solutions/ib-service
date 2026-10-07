<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SyncSettingsResultData extends Data
{
    public function __construct(public readonly int $created, public readonly int $updated, public readonly int $deleted, public readonly int $unchanged, public readonly bool $dry_run) {}
}
