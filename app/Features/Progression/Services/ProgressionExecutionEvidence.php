<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

final class ProgressionExecutionEvidence
{
    public string $operation = '';

    /** @var array<string, int> */
    public array $counts = [];

    /** @var array<string, list<string>> */
    public array $ids = [];

    public function reset(string $operation): void
    {
        $this->operation = $operation;
        $this->counts = [];
        $this->ids = ['evaluation_ids' => [], 'distribution_ids' => [], 'run_ids' => [], 'result_ids' => []];
    }

    public function count(string $name, int $amount = 1): void
    {
        $this->counts[$name] = ($this->counts[$name] ?? 0) + $amount;
    }

    public function id(string $type, string $id): void
    {
        if (! in_array($id, $this->ids[$type] ?? [], true)) {
            $this->ids[$type][] = $id;
        }
    }
}
