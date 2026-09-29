<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\InMemory;

use App\Features\Progression\Contracts\Repositories\ActivityDistributionRepositoryInterface;
use App\Features\Progression\Models\ActivityDistribution;
use Closure;
use Throwable;

final class InMemoryActivityDistributionRepository implements ActivityDistributionRepositoryInterface
{
    /** @var array<string, ActivityDistribution> */
    private array $items = [];

    /** @var array<string, string> */
    private array $index = [];

    public function transaction(Closure $callback): mixed
    {
        $items = unserialize(serialize($this->items), ['allowed_classes' => true]);
        $index = $this->index;
        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->items = $items;
            $this->index = $index;
            throw $throwable;
        }
    }

    public function findBySourceActivity(string $moduleId, string $sourceActivityId): ?ActivityDistribution
    {
        $id = $this->index[$moduleId.'|'.$sourceActivityId] ?? null;

        return $id === null ? null : $this->copy($this->items[$id]);
    }

    public function record(ActivityDistribution $distribution): ActivityDistribution
    {
        return $this->transaction(function () use ($distribution): ActivityDistribution {
            $existing = $this->findBySourceActivity($distribution->moduleId, $distribution->sourceActivityId);
            if ($existing !== null) {
                return $existing;
            }
            $stored = $this->copy($distribution);
            $this->items[$stored->id] = $stored;
            $this->index[$stored->idempotencyKey()] = $stored->id;

            return $this->copy($stored);
        });
    }

    private function copy(ActivityDistribution $distribution): ActivityDistribution
    {
        /** @var ActivityDistribution $copy */
        $copy = unserialize(serialize($distribution), ['allowed_classes' => true]);

        return $copy;
    }
}
