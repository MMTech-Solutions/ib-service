<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\Http\V1\Requests\ReplaceNegativePnlConfigurationRequest;
use Spatie\LaravelData\Data;

final class ReplaceNegativePnlConfigurationCommand extends Data
{
    /** @param list<array{module_id:string,server_group_id:string,rule_version_id:string}> $groups */
    public function __construct(public readonly string $plan_id, public readonly string $program_id, public readonly string $cadence, public readonly array $groups) {}

    public static function fromRequest(ReplaceNegativePnlConfigurationRequest $request): self
    {
        $data = $request->validated();

        return new self($data['plan'], $data['program'], $data['cadence'], $data['groups']);
    }
}
