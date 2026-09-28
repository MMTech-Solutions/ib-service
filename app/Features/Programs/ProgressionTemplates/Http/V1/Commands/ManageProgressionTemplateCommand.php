<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Commands;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use Spatie\LaravelData\Data;

final class ManageProgressionTemplateCommand extends Data
{
    /**
     * @param  list<ProgressionTemplateLevelCommandData>|null  $levels
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?int $lockVersion,
        public readonly ?array $levels,
    ) {}

    public static function fromRequest(ManageProgressionTemplateRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            description: $data['description'] ?? null,
            lockVersion: isset($data['lock_version']) ? (int) $data['lock_version'] : null,
            levels: isset($data['levels'])
                ? array_map(
                    static fn (array $level): ProgressionTemplateLevelCommandData => new ProgressionTemplateLevelCommandData(
                        distributionLevel: (int) $level['distribution_level'],
                        weight: (string) $level['weight'],
                    ),
                    $data['levels'],
                )
                : null,
        );
    }
}
