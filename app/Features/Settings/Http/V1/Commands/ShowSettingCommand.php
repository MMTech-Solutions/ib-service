<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Commands;

use App\Features\Settings\Http\V1\Requests\ShowSettingRequest;
use Spatie\LaravelData\Data;

final class ShowSettingCommand extends Data
{
    public function __construct(public readonly string $key) {}

    public static function fromRequest(ShowSettingRequest $request): self
    {
        return new self($request->validated('key'));
    }
}
