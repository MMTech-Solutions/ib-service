<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Strategies;

interface RuleStrategyDefinitionInterface
{
    public function type(): string;

    public function schemaVersion(): int;

    /** @return array<string, mixed> */
    public function jsonSchema(): array;

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(int $schemaVersion, array $configuration): void;
}
