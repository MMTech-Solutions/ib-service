<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Rules\Catalog\Actions\AssertRulePlanMutableAction;
use App\Features\Rules\Catalog\Actions\ValidateRuleConfigurationAction;
use App\Features\Rules\Catalog\DTOs\RuleVersionData;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\StoreRuleVersionCommand;
use App\Features\Rules\Catalog\Models\RuleVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreRuleVersionUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly AssertRulePlanMutableAction $assertPlan,
        private readonly ValidateRuleConfigurationAction $validateConfiguration,
    ) {}

    public function execute(StoreRuleVersionCommand $command): RuleVersionData
    {
        $this->assertPlan->assertMutable($command->planId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleVersionData {
            $rule = $repository->findByPlanAndId($command->planId, $command->ruleId);
            if ($rule === null) {
                throw RuleNotFoundException::forId($command->ruleId);
            }

            $this->validateConfiguration->validate(
                $rule->strategyType,
                $command->schemaVersion,
                $command->configuration,
            );

            $now = CarbonImmutable::now('UTC')->toISOString();
            $version = RuleVersion::draft(
                id: (string) Str::uuid7(),
                ruleId: $rule->id,
                versionNumber: $repository->nextVersionNumber($rule->id),
                schemaVersion: $command->schemaVersion,
                configuration: $command->configuration,
                now: $now,
            );
            $rule->updatedAt = $now;
            $repository->addVersion($rule, $version);

            return $version->toData();
        });
    }
}
