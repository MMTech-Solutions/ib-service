<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Actions;

use App\Features\Rules\Contracts\Strategies\RuleStrategyRegistryInterface;

final class ValidateRuleConfigurationAction
{
    public function __construct(private readonly RuleStrategyRegistryInterface $registry) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(string $strategyType, int $schemaVersion, array $configuration): void
    {
        $this->registry->validate($strategyType, $schemaVersion, $configuration);
    }

    public function assertKnown(string $strategyType): void
    {
        $this->registry->definition($strategyType);
    }
}
