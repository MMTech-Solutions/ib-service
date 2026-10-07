<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Commands;

use App\Features\Settings\Http\V1\Requests\ListSettingsRequest;
use Spatie\LaravelData\Data;

final class ListSettingsCommand extends Data
{
    public function __construct(public readonly int $page = 1, public readonly int $per_page = 100, public readonly ?string $search = null, public readonly ?string $domain = null, public readonly ?string $section = null, public readonly ?string $provider = null) {}

    public static function fromRequest(ListSettingsRequest $request): self
    {
        $data = $request->validated();

        return new self((int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 100), $data['search'] ?? null, $data['domain'] ?? null, $data['section'] ?? null, $data['provider'] ?? null);
    }
}
