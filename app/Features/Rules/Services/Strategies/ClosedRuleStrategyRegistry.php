<?php

declare(strict_types=1);

namespace App\Features\Rules\Services\Strategies;

use App\Features\Rules\Catalog\Exceptions\UnsupportedRuleStrategyException;
use App\Features\Rules\Contracts\Strategies\RuleStrategyDefinitionInterface;
use App\Features\Rules\Contracts\Strategies\RuleStrategyRegistryInterface;

final class ClosedRuleStrategyRegistry implements RuleStrategyRegistryInterface
{
    /** @var array<string, RuleStrategyDefinitionInterface> */
    private array $definitions;

    public function __construct()
    {
        $points = new PointsPerQuantityUnitStrategy;
        $this->definitions = [
            $points->type() => $points,
        ];
    }

    public function has(string $strategyType): bool
    {
        return isset($this->definitions[$strategyType]);
    }

    public function definition(string $strategyType): RuleStrategyDefinitionInterface
    {
        if (! $this->has($strategyType)) {
            throw UnsupportedRuleStrategyException::forType($strategyType);
        }

        return $this->definitions[$strategyType];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(string $strategyType, int $schemaVersion, array $configuration): void
    {
        $this->definition($strategyType)->validate($schemaVersion, $configuration);
    }
}
