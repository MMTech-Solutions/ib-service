<?php

declare(strict_types=1);

namespace App\Features\Rules\Services\Strategies;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Contracts\Strategies\RuleStrategyDefinitionInterface;

final class PointsPerQuantityUnitStrategy implements RuleStrategyDefinitionInterface
{
    public const TYPE = 'points_per_quantity_unit';

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
            'required' => ['unit', 'points_per_unit'],
            'properties' => [
                'unit' => [
                    'type' => 'string',
                    'pattern' => '^[a-z][a-z0-9_-]*$',
                    'maxLength' => 64,
                ],
                'points_per_unit' => [
                    'type' => 'string',
                    'pattern' => '^(?:0|[1-9]\\d*)(?:\\.\\d+)?$',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(int $schemaVersion, array $configuration): void
    {
        if ($schemaVersion !== $this->schemaVersion()) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Unsupported schema_version.');
        }

        $keys = array_keys($configuration);
        sort($keys);
        if ($keys !== ['points_per_unit', 'unit']) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Configuration must contain only unit and points_per_unit.');
        }

        $unit = $configuration['unit'];
        if (! is_string($unit) || preg_match('/^[a-z][a-z0-9_-]*$/', $unit) !== 1 || strlen($unit) > 64) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'unit must be a stable lowercase identifier.');
        }

        $points = $configuration['points_per_unit'];
        if (! is_string($points) || preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $points) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'points_per_unit must be a canonical positive decimal string.');
        }

        if (bccomp($points, '0', 8) !== 1) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'points_per_unit must be greater than zero.');
        }
    }
}
