<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

final class SchedulingOutputSecrets
{
    /** @return list<string> */
    public function all(): array
    {
        $secrets = $this->collect(config()->all());
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return array_values(array_unique($secrets));
    }

    /** @param array<string, mixed> $configuration @return list<string> */
    private function collect(array $configuration): array
    {
        $secrets = [];
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $secrets = [...$secrets, ...$this->collect($value)];
            } elseif (is_string($value) && strlen($value) >= 4 && preg_match('/secret|password|token|(^|_)key$/i', (string) $key)) {
                $secrets[] = $value;
            }
        }

        return $secrets;
    }
}
