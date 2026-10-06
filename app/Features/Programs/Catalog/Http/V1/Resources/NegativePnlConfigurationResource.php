<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NegativePnlConfigurationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $configuration = $this->resource;

        return [
            'id' => $configuration->id,
            'program_id' => $configuration->program_id,
            'cadence' => $configuration->cadence,
            'actor_id' => $configuration->actor_id,
            'starts_at' => $configuration->starts_at,
            'ends_at' => $configuration->ends_at,
            'closed_by_actor_id' => $configuration->closed_by_actor_id,
            'modules' => array_map(static fn ($group): array => [
                'module_id' => $group->module_id,
                'rule_version_id' => $group->rule_version_id,
            ], $configuration->modules),
        ];
    }
}
