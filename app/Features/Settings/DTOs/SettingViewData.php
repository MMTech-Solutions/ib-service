<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SettingViewData extends Data
{
    public function __construct(
        public readonly SettingDefinitionData $definition,
        public readonly bool $persisted,
        public readonly string $mode,
        public readonly string $source,
        public readonly ?int $lock_version,
        public readonly mixed $stored_value,
        public readonly mixed $effective_value,
        public readonly bool $configured,
        public readonly bool $redacted,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $payload = [...$this->definition->toArray(), 'persisted' => $this->persisted, 'mode' => $this->mode, 'source' => $this->source, 'lock_version' => $this->lock_version, 'configured' => $this->configured];
        $payload['sensitive'] = $this->redacted;
        if (! $this->redacted) {
            $payload['stored_value'] = $this->stored_value;
            $payload['effective_value'] = $this->effective_value;
        }

        return $payload;
    }
}
