<?php

declare(strict_types=1);

namespace App\Features\Rules\Services\Strategies;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Contracts\Strategies\RuleStrategyDefinitionInterface;

final class CpaFixedAmountStrategy implements RuleStrategyDefinitionInterface
{
    public const TYPE = 'cpa_fixed_amount';

    public function type(): string
    {
        return self::TYPE;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    /** @return array<string, mixed> */
    public function jsonSchema(): array
    {
        return ['type' => 'object', 'required' => ['amount', 'currency'], 'additionalProperties' => false];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(int $schemaVersion, array $configuration): void
    {
        $keys = array_keys($configuration);
        sort($keys);
        if ($schemaVersion !== 1 || $keys !== ['amount', 'currency']) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Configuration must contain amount and currency.');
        }
        if (! is_string($configuration['amount']) || bccomp($configuration['amount'], '0', 8) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'amount must be a positive decimal string.');
        }
        if (! is_string($configuration['currency']) || preg_match('/^[A-Z]{3}$/', $configuration['currency']) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'currency must be an ISO 4217 code.');
        }
    }
}
