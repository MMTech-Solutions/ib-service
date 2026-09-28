<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Actions;

use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ProgressionTemplateLevelCommandData;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateLevel;
use Illuminate\Support\Str;

final class BuildProgressionTemplateLevelsAction
{
    /**
     * @param  list<ProgressionTemplateLevelCommandData>  $levels
     * @return list<ProgressionTemplateLevel>
     */
    public function execute(array $levels): array
    {
        return array_map(
            static fn (ProgressionTemplateLevelCommandData $level): ProgressionTemplateLevel => new ProgressionTemplateLevel(
                id: (string) Str::uuid7(),
                distributionLevel: $level->distributionLevel,
                weight: $level->weight,
            ),
            $levels,
        );
    }
}
