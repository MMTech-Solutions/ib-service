<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Catalog\DTOs\RuleVersionData;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\ShowRuleVersionCommand;

final class ShowRuleVersionUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
    ) {}

    public function execute(ShowRuleVersionCommand $command): RuleVersionData
    {
        $this->plans->resolve($command->planId);
        $rule = $this->repositoryFactory->make()->findByPlanAndId($command->planId, $command->ruleId);
        if ($rule === null) {
            throw RuleNotFoundException::forId($command->ruleId);
        }

        $version = $rule->findVersion($command->versionId);
        if ($version === null) {
            throw RuleVersionNotFoundException::forId($command->versionId);
        }

        return $version->toData();
    }
}
