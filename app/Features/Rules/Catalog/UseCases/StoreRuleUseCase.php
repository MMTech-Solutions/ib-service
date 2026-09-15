<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Rules\Catalog\Actions\AssertRulePlanMutableAction;
use App\Features\Rules\Catalog\Actions\PresentRuleAction;
use App\Features\Rules\Catalog\Actions\ValidateRuleConfigurationAction;
use App\Features\Rules\Catalog\DTOs\RuleDetailData;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameConflictException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugConflictException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\StoreRuleCommand;
use App\Features\Rules\Catalog\Models\Rule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreRuleUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly AssertRulePlanMutableAction $assertPlan,
        private readonly ValidateRuleConfigurationAction $validateConfiguration,
        private readonly PresentRuleAction $presentRule,
    ) {}

    public function execute(StoreRuleCommand $command): RuleDetailData
    {
        $this->assertPlan->assertMutable($command->planId);
        $this->validateConfiguration->assertKnown($command->strategyType);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleDetailData {
            $rule = Rule::create(
                id: (string) Str::uuid7(),
                planId: $command->planId,
                name: $command->name,
                description: $command->description,
                strategyType: $command->strategyType,
                now: CarbonImmutable::now('UTC')->toISOString(),
            );

            try {
                $repository->create($rule);
            } catch (DuplicateRuleNameException) {
                throw DuplicateRuleNameConflictException::forName($command->name);
            } catch (DuplicateRuleSlugException) {
                throw DuplicateRuleSlugConflictException::forSlug($rule->slug);
            }

            return $this->presentRule->toDetail($rule);
        });
    }
}
