<?php

declare(strict_types=1);

namespace App\Features\Settings\Services;

use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\Exceptions\SettingException;

final class SettingValueValidator
{
    public function validate(SettingDefinitionData $definition, mixed $value): void
    {
        if ($value === null && $definition->nullable) {
            return;
        }
        $valid = match ($definition->type) {
            'boolean' => is_bool($value),
            'integer' => is_int($value) && ($definition->minimum === null || $value >= $definition->minimum),
            'decimal' => is_string($value) && preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D', $value) === 1,
            'string' => is_string($value),
            'url' => is_string($value) && ($value === '' || (filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true) && parse_url($value, PHP_URL_USER) === null && parse_url($value, PHP_URL_PASS) === null && parse_url($value, PHP_URL_QUERY) === null && parse_url($value, PHP_URL_FRAGMENT) === null)),
            'path' => is_string($value) && preg_match('#^/[a-zA-Z0-9_/-]+$#D', $value) === 1 && ! str_contains($value, '//'),
            'string_list' => is_array($value) && array_is_list($value) && count($value) <= 100 && array_all($value, static fn (mixed $item): bool => is_string($item) && strlen($item) <= 120),
            default => false,
        };
        if (! $valid || (is_string($value) && (strlen($value) > $definition->max_length || preg_match('/[\x00-\x1F\x7F]/', $value)))) {
            throw SettingException::invalid($definition->key);
        }
    }
}
