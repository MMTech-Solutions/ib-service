<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Rules\Assignments\Exceptions\RuleVersionNotAssignableException;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;

final class AssertPublishedRuleVersionAction
{
    public function __construct(private readonly RuleRepositoryFactory $ruleRepositoryFactory) {}

    public function assert(string $planId, string $ruleId, string $ruleVersionId): RuleVersion
    {
        $rule = $this->rule($planId, $ruleId);
        $version = $rule->findVersion($ruleVersionId);
        if ($version === null || ! $version->isPublished()) {
            throw RuleVersionNotAssignableException::forId($ruleVersionId);
        }

        return $version;
    }

    public function rule(string $planId, string $ruleId): Rule
    {
        $rule = $this->ruleRepositoryFactory->make()->findByPlanAndId($planId, $ruleId);
        if ($rule === null) {
            throw RuleNotFoundException::forId($ruleId);
        }

        return $rule;
    }
}
