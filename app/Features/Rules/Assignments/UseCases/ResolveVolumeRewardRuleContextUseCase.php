<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\ResolveVolumeRewardRuleContextQueryData;
use App\Features\Rules\Contracts\Data\V1\VolumeRewardRuleContextData;
use App\Features\Rules\Contracts\Exceptions\AmbiguousVolumeRewardRuleException;
use App\Features\Rules\Contracts\Ports\Input\ResolveVolumeRewardRuleContextPort;

final class ResolveVolumeRewardRuleContextUseCase implements ResolveVolumeRewardRuleContextPort
{
    public function __construct(private readonly RuleAssignmentRepositoryFactory $assignments, private readonly RuleRepositoryFactory $rules) {}

    public function execute(ResolveVolumeRewardRuleContextQueryData $query): VolumeRewardRuleContextData
    {
        $matches = [];
        foreach ($this->assignments->make()->listEffectiveAt($query->program_id, $query->module_id, $query->occurred_at) as $assignment) {
            $rule = $this->rules->make()->findById($assignment->ruleId);
            $version = $rule?->findVersion($assignment->ruleVersionId);
            if ($rule?->strategyType === RuleStrategyType::TradedVolumeCommission->value && $version?->isPublished() && ($version->configuration['unit_code'] ?? null) === $query->unit_code) {
                $matches[] = new VolumeRewardRuleContextData($assignment->id, $rule->id, $version->id);
            }
        }
        if ($matches === []) {
            return VolumeRewardRuleContextData::absent();
        }
        if (count($matches) !== 1) {
            throw AmbiguousVolumeRewardRuleException::forContext();
        }

        return $matches[0];
    }
}
