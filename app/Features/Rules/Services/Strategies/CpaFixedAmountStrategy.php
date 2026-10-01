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
        return 2;
    }

    /** @return array<string, mixed> */
    public function jsonSchema(): array
    {
        return ['type' => 'object', 'required' => ['amount', 'currency', 'required_volume', 'volume_unit_code', 'required_deposit'], 'additionalProperties' => false];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(int $schemaVersion, array $configuration): void
    {
        $keys = array_keys($configuration);
        sort($keys);
        if ($schemaVersion === 1 && $keys === ['amount', 'currency']) {
            return;
        }
        if ($schemaVersion !== 2 || $keys !== ['amount', 'currency', 'required_deposit', 'required_volume', 'volume_unit_code']) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Configuration must contain payment and CPA requirements.');
        }
        if (! is_string($configuration['amount']) || bccomp($configuration['amount'], '0', 8) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'amount must be a positive decimal string.');
        }
        if (! is_string($configuration['currency']) || preg_match('/^[A-Z]{3}$/', $configuration['currency']) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'currency must be an ISO 4217 code.');
        }
        if (! is_string($configuration['required_volume']) || bccomp($configuration['required_volume'], '0', 8) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'required_volume must be a positive decimal string.');
        }
        if (($configuration['volume_unit_code'] ?? null) !== 'lot') {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'volume_unit_code must be lot.');
        }
        if (! is_string($configuration['required_deposit']) || bccomp($configuration['required_deposit'], '0', 8) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'required_deposit must be a positive decimal string.');
        }
    }
}
