<?php

declare(strict_types=1);

namespace App\Features\Settings\Services;

use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\Exceptions\SettingException;

final class SettingDefinitionRegistry
{
    /** @param list<SettingDefinitionData>|null $definitions */
    public function __construct(private readonly ?array $definitions = null) {}

    /** @return list<SettingDefinitionData> */
    public function all(): array
    {
        $definitions = $this->definitions ?? $this->catalog();
        $keys = [];
        foreach ($definitions as $definition) {
            if (isset($keys[$definition->key]) || ! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D', $definition->key) || ! in_array($definition->domain, ['Rewards', 'Modules', 'Finance'], true) || $definition->section === '' || $definition->schema_version < 1 || $definition->max_length < 1 || ($definition->sensitive && $definition->type !== 'string') || ! in_array($definition->type, ['string', 'integer', 'boolean', 'decimal', 'url', 'path', 'string_list'], true)) {
                throw SettingException::invalid($definition->key);
            }
            $keys[$definition->key] = true;
        }
        usort($definitions, static fn (SettingDefinitionData $a, SettingDefinitionData $b): int => [$a->domain, $a->section, $a->provider ?? '', $a->position, $a->key] <=> [$b->domain, $b->section, $b->provider ?? '', $b->position, $b->key]);

        return $definitions;
    }

    public function find(string $key): SettingDefinitionData
    {
        foreach ($this->all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }
        throw SettingException::unknown($key);
    }

    /** @return list<SettingDefinitionData> */
    private function catalog(): array
    {
        $definitions = [];
        $add = static function (string $key, string $domain, string $section, string $type, ?string $provider = null, bool $nullable = false, bool $sensitive = false, ?int $minimum = null) use (&$definitions): void {
            $label = ucfirst(str_replace('_', ' ', substr($key, strrpos($key, '.') + 1)));
            $definitions[] = new SettingDefinitionData($key, $domain, $section, $provider, count($definitions), $label, "{$domain}: {$section} — {$label}.", $type, $nullable, $sensitive, minimum: $minimum);
        };
        $add('rewards.minimum_amount_major', 'Rewards', 'General', 'decimal');
        foreach ([
            'volume' => ['retry_delay_seconds', 'batch_size', 'claim_lease_seconds'],
            'negative_pnl' => ['subject_batch_size', 'batch_size', 'discovery_batch_size', 'claim_lease_seconds', 'retry_delay_seconds', 'broker_timeout_seconds'],
            'cpa' => ['batch_size'],
            'settlement' => ['batch_size', 'retry_delay_seconds', 'claim_lease_seconds'],
            'reconciliation' => ['batch_size'],
        ] as $section => $keys) {
            foreach ($keys as $key) {
                $add("rewards.{$section}.{$key}", 'Rewards', $section, 'integer', minimum: $key === 'retry_delay_seconds' ? 0 : 1);
            }
        }
        foreach (['enabled', 'settlement_enabled'] as $key) {
            $add("rewards.negative_pnl.{$key}", 'Rewards', 'negative_pnl', 'boolean');
        }
        foreach (['topic', 'source'] as $key) {
            $add("rewards.events.{$key}", 'Rewards', 'events', 'string');
        }
        $add('rewards.auth_account_registered.topic', 'Rewards', 'auth_account_registered', 'string');
        $add('rewards.auth_account_registered.allowed_sources', 'Rewards', 'auth_account_registered', 'string_list');
        foreach (['broker', 'copy_trading'] as $provider) {
            foreach (['topic' => 'string', 'base_url' => 'url', 'internal_prefix' => 'path', 'internal_token' => 'string', 'source_service' => 'string', 'timeout_seconds' => 'integer'] as $key => $type) {
                $add("modules.sources.{$provider}.{$key}", 'Modules', 'Connections', $type, $provider, $key === 'internal_token', $key === 'internal_token', $type === 'integer' ? 1 : null);
            }
        }
        foreach (['default_limit', 'max_limit', 'max_range_days', 'timeout_seconds'] as $key) {
            $add("modules.activity.{$key}", 'Modules', 'Activity', 'integer', minimum: 1);
        }
        foreach (['base_url' => 'url', 'internal_token' => 'string', 'source_service' => 'string', 'timeout_seconds' => 'integer'] as $key => $type) {
            $add("finance.{$key}", 'Finance', 'Connection', $type, nullable: $key === 'internal_token', sensitive: $key === 'internal_token', minimum: $type === 'integer' ? 1 : null);
        }

        return $definitions;
    }
}
