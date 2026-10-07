<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Commands;

use App\Features\Settings\Http\V1\Requests\CertifySettingsConnectionRequest;
use Spatie\LaravelData\Data;

final class CertifySettingsConnectionCommand extends Data
{
    public function __construct(public readonly string $provider) {}

    public static function fromRequest(CertifySettingsConnectionRequest $request): self
    {
        return new self($request->validated('provider'));
    }
}
