<?php

declare(strict_types=1);

namespace App\Features\Rules\Services\Strategies;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Contracts\Strategies\RuleStrategyDefinitionInterface;

final class NegativePnlShareStrategy implements RuleStrategyDefinitionInterface
{
    public const TYPE = 'negative_pnl_share';

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
            'required' => ['plan_payment_template_version_binding_id'],
            'properties' => [
                'plan_payment_template_version_binding_id' => ['type' => 'string', 'format' => 'uuid'],
            ],
        ];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(int $schemaVersion, array $configuration): void
    {
        $keys = array_keys($configuration);
        sort($keys);
        if ($schemaVersion !== $this->schemaVersion() || $keys !== ['plan_payment_template_version_binding_id']) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'Configuration must contain a payment template binding.');
        }

        $bindingId = $configuration['plan_payment_template_version_binding_id'] ?? null;
        if (! is_string($bindingId) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $bindingId)) {
            throw InvalidRuleConfigurationException::forStrategy(self::TYPE, 'plan_payment_template_version_binding_id must be a UUID.');
        }
    }
}
