<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Commands;

use App\Features\Settings\Http\V1\Requests\ResetSettingRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ResetSettingCommand extends Data
{
    public function __construct(public readonly string $key, public readonly int $lock_version, public readonly string $reason, public readonly string $actor) {}

    public static function fromRequest(ResetSettingRequest $request, UserContext $context): self
    {
        $data = $request->validated();

        return new self($data['key'], (int) $data['lock_version'], $data['reason'], $context->id());
    }
}
