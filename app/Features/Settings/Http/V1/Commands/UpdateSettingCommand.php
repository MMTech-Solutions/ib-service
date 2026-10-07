<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Commands;

use App\Features\Settings\Http\V1\Requests\UpdateSettingRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class UpdateSettingCommand extends Data
{
    public function __construct(public readonly string $key, public readonly mixed $value, public readonly int $lock_version, public readonly string $reason, public readonly string $actor) {}

    public static function fromRequest(UpdateSettingRequest $request, UserContext $context): self
    {
        $data = $request->validated();

        return new self($data['key'], $data['value'], (int) $data['lock_version'], $data['reason'], $context->id());
    }
}
