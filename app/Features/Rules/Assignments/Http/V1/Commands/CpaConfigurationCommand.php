<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Commands;

use App\Features\Rules\Assignments\Http\V1\Requests\CpaConfigurationRequest;
use Spatie\LaravelData\Data;

final class CpaConfigurationCommand extends Data
{
    public function __construct(public readonly string $program_id, public readonly ?string $rule_version_id, public readonly string $operation) {}

    public static function fromRequest(CpaConfigurationRequest $request): self
    {
        return new self($request->validated('program'), $request->validated('rule_version_id'), $request->method());
    }
}
