<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\InMemory;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\DTOs\ActivityEvaluationAggregatePageData;
use App\Features\Progression\DTOs\ActivityEvaluationListQueryData;
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
        ?int $distributionLevel = null,
    ): ?ActivityEvaluation {
        $key = $this->key($moduleId, $sourceActivityId, $beneficiaryExternalUserId, $distributionLevel);
        $id = $this->idempotencyIndex[$key] ?? null;

        return $id === null ? null : $this->findById($id);
    }

    public function paginate(ActivityEvaluationListQueryData $query): ActivityEvaluationAggregatePageData
    {
        $filtered = array_values(array_filter(
            $this->byId,
            static function (ActivityEvaluation $evaluation) use ($query): bool {
                if ($query->planId !== null && $evaluation->planId !== $query->planId) {
                    return false;
                }

                if ($query->subscriptionId !== null && $evaluation->subscriptionId !== $query->subscriptionId) {
                    return false;
                }

                if ($query->status !== null && $evaluation->status !== $query->status) {
                    return false;
                }

                if (
                    $query->exclusionReason !== null
                    && $evaluation->exclusionReason !== $query->exclusionReason
                ) {
                    return false;
                }

                if (
                    $query->occurredAtFrom !== null
                    && $evaluation->occurredAt->lt($query->occurredAtFrom)
                ) {
                    return false;
                }

                return $query->occurredAtTo === null
                    || ! $evaluation->occurredAt->gt($query->occurredAtTo);
            },
        ));

        usort(
            $filtered,
            static function (ActivityEvaluation $left, ActivityEvaluation $right): int {
                $occurredCompare = $right->occurredAt <=> $left->occurredAt;
                if ($occurredCompare !== 0) {
                    return $occurredCompare;
                }

                return strcmp($right->id, $left->id);
            },
        );

        $total = count($filtered);
        $offset = ($query->page - 1) * $query->perPage;
        $evaluations = array_map(
            fn (ActivityEvaluation $evaluation): ActivityEvaluation => $this->copy($evaluation),
            array_slice($filtered, $offset, $query->perPage),
        );

        return new ActivityEvaluationAggregatePageData(
            evaluations: $evaluations,
            currentPage: $query->page,
            perPage: $query->perPage,
            total: $total,
            lastPage: max(1, (int) ceil($total / max(1, $query->perPage))),
        );
    }

    public function record(ActivityEvaluation $evaluation): ActivityEvaluation
    {
        return $this->transaction(function () use ($evaluation): ActivityEvaluation {
            $existing = $this->findByIdempotencyKey(
                $evaluation->moduleId,
                $evaluation->sourceActivityId,
                $evaluation->beneficiaryExternalUserId,
                $evaluation->distributionLevel,
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
        ?int $distributionLevel = null,
    ): string {
        return implode('|', [$moduleId, $sourceActivityId, $beneficiaryExternalUserId, $distributionLevel ?? -1]);
    }

    private function copy(ActivityEvaluation $evaluation): ActivityEvaluation
    {
        /** @var ActivityEvaluation $copy */
        $copy = unserialize(serialize($evaluation), ['allowed_classes' => true]);

        return $copy;
    }
}
