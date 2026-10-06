<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Repositories\InMemory;

use App\Features\Rules\Assignments\Contracts\Repositories\CpaConfigurationRepositoryInterface;
use App\Features\Rules\Assignments\DTOs\CpaConfigurationData;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class InMemoryCpaConfigurationRepository implements CpaConfigurationRepositoryInterface
{
    /** @var list<CpaConfigurationData> */
    private array $assignments = [];

    public function __construct(private readonly RuleRepositoryFactory $rules) {}

    public function resolve(string $programId, string $at): CpaRuleContextData
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->program_id === $programId && CarbonImmutable::parse($assignment->starts_at)->lte(CarbonImmutable::parse($at)) && ($assignment->ends_at === null || CarbonImmutable::parse($assignment->ends_at)->gt(CarbonImmutable::parse($at)))) {
                $rule = $this->rules->make('memory')->findById($assignment->rule_id);

                return new CpaRuleContextData($assignment->id, $assignment->rule_id, $assignment->rule_version_id, $rule?->findVersion($assignment->rule_version_id)?->configuration);
            }
        }

        return CpaRuleContextData::absent();
    }

    public function publishedVersion(string $planId, string $versionId): ?array
    {
        foreach ($this->rules->make('memory')->listByPlanId($planId) as $rule) {
            $version = $rule->findVersion($versionId);
            if ($rule->strategyType === 'cpa_fixed_amount' && $version?->isPublished()) {
                return ['rule_id' => $rule->id, 'configuration' => $version->configuration];
            }
        }

        return null;
    }

    public function current(string $programId): ?CpaConfigurationData
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->program_id === $programId && $assignment->ends_at === null) {
                return $assignment;
            }
        }

        return null;
    }

    public function replace(string $programId, string $ruleId, string $versionId, string $actorId, string $at): CpaConfigurationData
    {
        $current = $this->current($programId);
        if ($current?->rule_version_id === $versionId) {
            return $current;
        }
        $this->withdraw($programId, $actorId, $at);
        $assignment = new CpaConfigurationData((string) Str::uuid7(), $programId, $ruleId, $versionId, CarbonImmutable::parse($at)->toIso8601String(), null);
        $this->assignments[] = $assignment;

        return $assignment;
    }

    public function withdraw(string $programId, string $actorId, string $at): void
    {
        foreach ($this->assignments as $index => $assignment) {
            if ($assignment->program_id === $programId && $assignment->ends_at === null) {
                $this->assignments[$index] = new CpaConfigurationData($assignment->id, $assignment->program_id, $assignment->rule_id, $assignment->rule_version_id, $assignment->starts_at, CarbonImmutable::parse($at)->toIso8601String());
            }
        }
    }
}
