<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SettingsPageData extends Data
{
    /** @param list<SettingViewData> $items */
    public function __construct(public readonly array $items, public readonly int $total, public readonly int $page, public readonly int $per_page) {}
}
