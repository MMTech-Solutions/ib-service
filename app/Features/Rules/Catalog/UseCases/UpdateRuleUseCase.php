<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Rules\Catalog\Actions\AssertRulePlanMutableAction;
use App\Features\Rules\Catalog\Actions\PresentRuleAction;
use App\Features\Rules\Catalog\DTOs\RuleDetailData;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameConflictException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugConflictException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugException;
use App\Features\Rules\Catalog\Exceptions\RuleConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\UpdateRuleCommand;
use Carbon\CarbonImmutable;

final class UpdateRuleUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly AssertRulePlanMutableAction $assertPlan,
        private readonly PresentRuleAction $presentRule,
    ) {}

    public function execute(UpdateRuleCommand $command): RuleDetailData
    {
        $this->assertPlan->assertMutable($command->planId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleDetailData {
            $rule = $repository->findByPlanAndId($command->planId, $command->ruleId);
            if ($rule === null) {
                throw RuleNotFoundException::forId($command->ruleId);
            }

            if ($rule->lockVersion !== $command->lockVersion) {
                throw RuleConcurrencyException::forRule($command->ruleId);
            }

            $changed = $rule->updateAdministrativeFields(
                $command->hasName ? (string) $command->name : $rule->name,
                $command->hasDescription ? $command->description : $rule->description,
                CarbonImmutable::now('UTC')->toISOString(),
            );

            if ($changed) {
                try {
                    $repository->update($rule, $command->lockVersion);
                } catch (DuplicateRuleNameException) {
                    throw DuplicateRuleNameConflictException::forName($rule->name);
                } catch (DuplicateRuleSlugException) {
                    throw DuplicateRuleSlugConflictException::forSlug($rule->slug);
                }
            }

            return $this->presentRule->toDetail($rule);
        });
    }
}
