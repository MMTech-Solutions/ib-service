<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\NegativePnlRuleContextData;
use App\Features\Rules\Contracts\Data\V1\ResolveNegativePnlRuleContextQueryData;
use App\Features\Rules\Contracts\Exceptions\InvalidNegativePnlRuleContextException;
use App\Features\Rules\Contracts\Ports\Input\ResolveNegativePnlRuleContextPort;

final class ResolveNegativePnlRuleContextUseCase implements ResolveNegativePnlRuleContextPort
{
    public function __construct(private readonly RuleAssignmentRepositoryFactory $assignments, private readonly RuleRepositoryFactory $rules) {}

    public function execute(ResolveNegativePnlRuleContextQueryData $query): NegativePnlRuleContextData
    {
        $matches = [];
        foreach ($this->assignments->make()->listEffectiveAt($query->program_id, $query->module_id, $query->occurred_at) as $assignment) {
            if ($assignment->ruleVersionId !== $query->rule_version_id) {
                continue;
            }
            $rule = $this->rules->make()->findByPlanAndId($query->plan_id, $assignment->ruleId);
            $version = $rule?->findVersion($query->rule_version_id);
            if ($rule?->strategyType !== 'negative_pnl_share' || ! $version?->isPublished()) {
                continue;
            }
            $matches[] = new NegativePnlRuleContextData($assignment->id, $rule->id, $version->id, $version->configuration['plan_payment_template_version_binding_id']);
        }
        if (count($matches) !== 1) {
            throw InvalidNegativePnlRuleContextException::forContext();
        }

        return $matches[0];
    }
}
