<?php

declare(strict_types=1);

namespace App\Features\Rules\Services\Strategies;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Contracts\Strategies\RuleStrategyDefinitionInterface;

final class TradedVolumeCommissionStrategy implements RuleStrategyDefinitionInterface
{
    public const TYPE = 'traded_volume_commission';

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
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['unit_code'],
            'properties' => [
                'unit_code' => ['type' => 'string', 'const' => 'lot'],
            ],
        ];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(int $schemaVersion, array $configuration): void
    {
        $keys = array_keys($configuration);
        sort($keys);
        if ($schemaVersion !== $this->schemaVersion() || $keys !== ['unit_code'] || ($configuration['unit_code'] ?? null) !== 'lot') {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Configuration must contain unit_code lot.');
        }
    }
}
