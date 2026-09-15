<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Strategies;

interface RuleStrategyRegistryInterface
{
    public function has(string $strategyType): bool;

    public function definition(string $strategyType): RuleStrategyDefinitionInterface;

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(string $strategyType, int $schemaVersion, array $configuration): void;
}
