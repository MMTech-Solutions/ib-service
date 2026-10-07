<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Commands;

use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\Http\V1\Requests\ReadSchedulingRequest;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ReadSchedulingCommand extends Data
{
    public function __construct(public readonly ReadQueryData $query) {}

    public static function fromRequest(ReadSchedulingRequest $request): self
    {
        $v = $request->validated();

        return new self(new ReadQueryData((string) $request->route('scheduling_resource'), $v['code'] ?? null, $v['id'] ?? null, (int) ($v['page'] ?? 1), (int) ($v['per_page'] ?? 100), $v['status'] ?? null, $v['origin'] ?? null, isset($v['from']) ? CarbonImmutable::parse($v['from'])->utc()->toISOString() : null, isset($v['until']) ? CarbonImmutable::parse($v['until'])->utc()->toISOString() : null));
    }
}
