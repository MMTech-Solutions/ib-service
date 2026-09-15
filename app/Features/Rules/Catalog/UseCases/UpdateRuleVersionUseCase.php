<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Rules\Catalog\Actions\AssertRulePlanMutableAction;
use App\Features\Rules\Catalog\Actions\ValidateRuleConfigurationAction;
use App\Features\Rules\Catalog\DTOs\RuleVersionData;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\UpdateRuleVersionCommand;
use Carbon\CarbonImmutable;

final class UpdateRuleVersionUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly AssertRulePlanMutableAction $assertPlan,
        private readonly ValidateRuleConfigurationAction $validateConfiguration,
    ) {}

    public function execute(UpdateRuleVersionCommand $command): RuleVersionData
    {
        $this->assertPlan->assertMutable($command->planId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleVersionData {
            $rule = $repository->findByPlanAndId($command->planId, $command->ruleId);
            if ($rule === null) {
                throw RuleNotFoundException::forId($command->ruleId);
            }

            $version = $rule->findVersion($command->versionId);
            if ($version === null) {
                throw RuleVersionNotFoundException::forId($command->versionId);
            }

            if ($version->lockVersion !== $command->lockVersion) {
                throw RuleVersionConcurrencyException::forVersion($command->versionId);
            }

            $this->validateConfiguration->validate(
                $rule->strategyType,
                $command->schemaVersion,
                $command->configuration,
            );

            $changed = $version->replaceConfiguration(
                $command->schemaVersion,
                $command->configuration,
                CarbonImmutable::now('UTC')->toISOString(),
            );

            if ($changed) {
                $repository->updateVersion($version, $command->lockVersion);
            }

            return $version->toData();
        });
    }
}
