<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\InMemory;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\Models\ActivityEvaluation;
use Closure;
use Throwable;

final class InMemoryActivityEvaluationRepository implements ActivityEvaluationRepositoryInterface
{
    /** @var array<string, ActivityEvaluation> */
    private array $byId = [];

    /** @var array<string, string> */
    private array $idempotencyIndex = [];

    public function transaction(Closure $callback): mixed
    {
        $byIdSnapshot = unserialize(serialize($this->byId), ['allowed_classes' => true]);
        $indexSnapshot = $this->idempotencyIndex;

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->byId = $byIdSnapshot;
            $this->idempotencyIndex = $indexSnapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?ActivityEvaluation
    {
        $evaluation = $this->byId[$id] ?? null;

        return $evaluation === null ? null : $this->copy($evaluation);
    }

    public function findByIdempotencyKey(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
    ): ?ActivityEvaluation {
        $key = $this->key($moduleId, $sourceActivityId, $beneficiaryExternalUserId);
        $id = $this->idempotencyIndex[$key] ?? null;

        return $id === null ? null : $this->findById($id);
    }

    public function record(ActivityEvaluation $evaluation): ActivityEvaluation
    {
        return $this->transaction(function () use ($evaluation): ActivityEvaluation {
            $existing = $this->findByIdempotencyKey(
                $evaluation->moduleId,
                $evaluation->sourceActivityId,
                $evaluation->beneficiaryExternalUserId,
            );

            if ($existing !== null) {
                return $existing;
            }

            $stored = $this->copy($evaluation);
            $this->byId[$stored->id] = $stored;
            $this->idempotencyIndex[$stored->idempotencyKey()] = $stored->id;

            return $this->copy($stored);
        });
    }

    private function key(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
    ): string {
        return implode('|', [$moduleId, $sourceActivityId, $beneficiaryExternalUserId]);
    }

    private function copy(ActivityEvaluation $evaluation): ActivityEvaluation
    {
        /** @var ActivityEvaluation $copy */
        $copy = unserialize(serialize($evaluation), ['allowed_classes' => true]);

        return $copy;
    }
}
