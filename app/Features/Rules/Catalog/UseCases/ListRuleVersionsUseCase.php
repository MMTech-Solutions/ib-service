<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Catalog\DTOs\RuleVersionData;
use App\Features\Rules\Catalog\DTOs\RuleVersionsPageData;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\ListRuleVersionsCommand;
use App\Features\Rules\Catalog\Models\RuleVersion;

final class ListRuleVersionsUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
    ) {}

    public function execute(ListRuleVersionsCommand $command): RuleVersionsPageData
    {
        $this->plans->resolve(new ResolvePlanContextQueryData(plan_id: $command->planId));
        $rule = $this->repositoryFactory->make()->findByPlanAndId($command->planId, $command->ruleId);
        if ($rule === null) {
            throw RuleNotFoundException::forId($command->ruleId);
        }

        return new RuleVersionsPageData(
            versions: array_map(
                static fn (RuleVersion $version): RuleVersionData => $version->toData(),
                $rule->versions,
            ),
        );
    }
}
