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
        $decimal = ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)(\\.[0-9]{1,8})?$'];
        $currency = ['type' => 'string', 'pattern' => '^[A-Z]{3}$'];
        $precision = ['type' => 'integer', 'minimum' => 0, 'maximum' => 8];
        $properties = [
            'amount' => $decimal, 'currency' => $currency, 'currency_precision' => $precision,
            'required_volume_points' => $decimal, 'required_deposit_points' => $decimal,
            'deposit_currency' => $currency, 'deposit_currency_precision' => $precision,
            'deposit_points_per_unit' => $decimal,
            'volume_modules' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['module_id', 'unit', 'points_per_unit'],
                'properties' => ['module_id' => ['type' => 'string', 'format' => 'uuid'], 'unit' => ['const' => 'lot'], 'points_per_unit' => $decimal],
            ]],
        ];

        return ['type' => 'object', 'required' => array_keys($properties), 'additionalProperties' => false, 'properties' => $properties];
    }

    /** @param array<string, mixed> $configuration */
    public function validate(int $schemaVersion, array $configuration): void
    {
        $keys = array_keys($configuration);
        $expected = $this->jsonSchema()['required'];
        sort($keys);
        sort($expected);
        if ($schemaVersion !== 1 || $keys !== $expected) {
            $this->invalid('Payment, independent point thresholds and conversions are required.');
        }
        foreach (['amount', 'required_volume_points', 'required_deposit_points', 'deposit_points_per_unit'] as $field) {
            $this->positiveDecimal($configuration[$field]);
        }
        foreach (['currency', 'deposit_currency'] as $field) {
            if (! is_string($configuration[$field]) || preg_match('/^[A-Z]{3}$/D', $configuration[$field]) !== 1) {
                $this->invalid('Currencies must be uppercase three-letter codes.');
            }
        }
        foreach (['currency_precision', 'deposit_currency_precision'] as $field) {
            if (! is_int($configuration[$field]) || $configuration[$field] < 0 || $configuration[$field] > 8) {
                $this->invalid('Currency precision must be between zero and eight.');
            }
        }
        if (bccomp($configuration['amount'], bcadd($configuration['amount'], '0', $configuration['currency_precision']), 8) !== 0) {
            $this->invalid('Payment amount exceeds currency precision.');
        }
        $modules = $configuration['volume_modules'];
        if (! is_array($modules) || ! array_is_list($modules) || count($modules) < 1 || count($modules) > 100) {
            $this->invalid('One to one hundred volume modules are required.');
        }
        $seen = [];
        foreach ($modules as $module) {
            if (! is_array($module)) {
                $this->invalid('Invalid module conversion.');
            }
            $moduleKeys = array_keys($module);
            sort($moduleKeys);
            if ($moduleKeys !== ['module_id', 'points_per_unit', 'unit'] || ! is_string($module['module_id']) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $module['module_id']) !== 1 || $module['unit'] !== 'lot' || isset($seen[strtolower($module['module_id'])])) {
                $this->invalid('Modules must be unique UUIDs with lot conversions.');
            }
            $this->positiveDecimal($module['points_per_unit']);
            $seen[strtolower($module['module_id'])] = true;
        }
    }

    private function positiveDecimal(mixed $value): void
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]{0,15})(\\.[0-9]{1,8})?$/D', $value) !== 1 || bccomp($value, '0', 8) <= 0) {
            $this->invalid('Positive decimal strings with at most eight decimals are required.');
        }
    }

    private function invalid(string $message): never
    {
        throw InvalidRuleConfigurationException::forStrategy(self::TYPE, $message);
    }
}
