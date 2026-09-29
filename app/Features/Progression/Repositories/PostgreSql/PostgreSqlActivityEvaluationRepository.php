<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\DTOs\ActivityEvaluationAggregatePageData;
use App\Features\Progression\DTOs\ActivityEvaluationListQueryData;
use App\Features\Progression\Enums\ContributionScopeType;
use App\Features\Progression\Enums\ContributionStrategyType;
use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Exceptions\ActivityEvaluationNotFoundException;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\Repositories\PostgreSql\Models\ActivityEvaluationRecord;
use App\Features\Progression\Repositories\PostgreSql\Models\ContributionRecord;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlActivityEvaluationRepository implements ActivityEvaluationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?ActivityEvaluation
    {
        $record = ActivityEvaluationRecord::query()
            ->with('contribution')
            ->whereKey($id)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByIdempotencyKey(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
        ?int $distributionLevel = null,
    ): ?ActivityEvaluation {
        $record = ActivityEvaluationRecord::query()
            ->with('contribution')
            ->where('module_id', $moduleId)
            ->where('source_activity_id', $sourceActivityId)
            ->where('beneficiary_external_user_id', $beneficiaryExternalUserId)
            ->where('distribution_level', $distributionLevel ?? 0)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function paginate(ActivityEvaluationListQueryData $query): ActivityEvaluationAggregatePageData
    {
        $builder = ActivityEvaluationRecord::query()
            ->with('contribution')
            ->when($query->planId !== null, fn ($builder) => $builder->where('plan_id', $query->planId))
            ->when(
                $query->subscriptionId !== null,
                fn ($builder) => $builder->where('subscription_id', $query->subscriptionId),
            )
            ->when($query->status !== null, fn ($builder) => $builder->where('status', $query->status->value))
            ->when(
                $query->exclusionReason !== null,
                fn ($builder) => $builder->where('exclusion_reason', $query->exclusionReason->value),
            )
            ->when(
                $query->occurredAtFrom !== null,
                fn ($builder) => $builder->where('occurred_at', '>=', $query->occurredAtFrom),
            )
            ->when(
                $query->occurredAtTo !== null,
                fn ($builder) => $builder->where('occurred_at', '<=', $query->occurredAtTo),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $paginator = $builder->paginate($query->perPage, ['*'], 'page', $query->page);
        $evaluations = collect($paginator->items())
            ->map(fn (ActivityEvaluationRecord $record): ActivityEvaluation => $this->hydrate($record))
            ->all();

        return new ActivityEvaluationAggregatePageData(
            evaluations: $evaluations,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function record(ActivityEvaluation $evaluation): ActivityEvaluation
    {
        return $this->transaction(function () use ($evaluation): ActivityEvaluation {
            $this->connection->statement('SAVEPOINT progression_record_evaluation');

            try {
                ActivityEvaluationRecord::query()->create($this->evaluationAttributes($evaluation));

                if ($evaluation->contribution !== null) {
                    ContributionRecord::query()->create($this->contributionAttributes($evaluation->contribution));
                }

                $this->connection->statement('RELEASE SAVEPOINT progression_record_evaluation');
            } catch (UniqueConstraintViolationException) {
                $this->connection->statement('ROLLBACK TO SAVEPOINT progression_record_evaluation');

                $canonical = $this->findByIdempotencyKey(
                    $evaluation->moduleId,
                    $evaluation->sourceActivityId,
                    $evaluation->beneficiaryExternalUserId,
                    $evaluation->distributionLevel,
                );

                if ($canonical === null) {
                    throw ActivityEvaluationNotFoundException::forIdempotencyKey(
                        $evaluation->moduleId,
                        $evaluation->sourceActivityId,
                        $evaluation->beneficiaryExternalUserId,
                    );
                }

                return $canonical;
            }

            $stored = $this->findById($evaluation->id);
            if ($stored === null) {
                throw ActivityEvaluationNotFoundException::forIdempotencyKey(
                    $evaluation->moduleId,
                    $evaluation->sourceActivityId,
                    $evaluation->beneficiaryExternalUserId,
                    $evaluation->distributionLevel,
                );
            }

            return $stored;
        });
    }

    private function hydrate(ActivityEvaluationRecord $record): ActivityEvaluation
    {
        $window = null;
        if ($record->window_starts_at !== null && $record->window_ends_at !== null) {
            $window = ProgressionWindow::of($record->window_starts_at, $record->window_ends_at);
        }

        $contribution = null;
        $contributionRecord = $record->relationLoaded('contribution')
            ? $record->getRelation('contribution')
            : $record->contribution()->first();

        if ($contributionRecord instanceof ContributionRecord) {
            $contribution = Contribution::reconstitute(
                id: (string) $contributionRecord->id,
                evaluationId: (string) $contributionRecord->evaluation_id,
                ruleId: (string) $contributionRecord->rule_id,
                ruleVersionId: (string) $contributionRecord->rule_version_id,
                ruleAssignmentId: (string) $contributionRecord->rule_assignment_id,
                strategyType: ContributionStrategyType::from((string) $contributionRecord->strategy_type),
                scopeType: ContributionScopeType::from((string) $contributionRecord->scope_type),
                weight: ExactDecimal::fromString((string) $contributionRecord->weight),
                distributionWeight: ExactDecimal::fromString((string) $contributionRecord->distribution_weight),
                points: ExactDecimal::fromString((string) $contributionRecord->points),
                createdAt: $contributionRecord->created_at,
                updatedAt: $contributionRecord->updated_at,
                programSymbolConfigurationId: $contributionRecord->program_symbol_configuration_id === null ? null : (string) $contributionRecord->program_symbol_configuration_id,
                planProgressionTemplateVersionBindingId: $contributionRecord->plan_progression_template_version_binding_id === null ? null : (string) $contributionRecord->plan_progression_template_version_binding_id,
                progressionTemplateVersionId: $contributionRecord->progression_template_version_id === null ? null : (string) $contributionRecord->progression_template_version_id,
            );
        }

        return ActivityEvaluation::reconstitute(
            id: (string) $record->id,
            moduleId: (string) $record->module_id,
            sourceActivityId: (string) $record->source_activity_id,
            activityDistributionId: $record->activity_distribution_id === null ? null : (string) $record->activity_distribution_id,
            sourceExternalUserId: $record->source_external_user_id === null ? null : (string) $record->source_external_user_id,
            distributionLevel: $record->distribution_level === null ? null : (int) $record->distribution_level,
            distributionResolvedAt: $record->distribution_resolved_at,
            beneficiaryExternalUserId: (string) $record->beneficiary_external_user_id,
            subscriptionId: $record->subscription_id === null ? null : (string) $record->subscription_id,
            planId: $record->plan_id === null ? null : (string) $record->plan_id,
            programId: $record->program_id === null ? null : (string) $record->program_id,
            occurredAt: $record->occurred_at,
            window: $window,
            metricCode: (string) $record->metric_code,
            unitCode: (string) $record->unit_code,
            instrumentReference: $record->instrument_reference === null
                ? null
                : (string) $record->instrument_reference,
            quantity: ExactDecimal::fromString((string) $record->quantity),
            status: EvaluationStatus::from((string) $record->status),
            exclusionReason: $record->exclusion_reason === null
                ? null
                : ExclusionReason::from((string) $record->exclusion_reason),
            evaluatedAt: $record->evaluated_at,
            createdAt: $record->created_at,
            updatedAt: $record->updated_at,
            contribution: $contribution,
        );
    }

    /** @return array<string, mixed> */
    private function evaluationAttributes(ActivityEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'module_id' => $evaluation->moduleId,
            'source_activity_id' => $evaluation->sourceActivityId,
            'activity_distribution_id' => $evaluation->activityDistributionId,
            'source_external_user_id' => $evaluation->sourceExternalUserId,
            'distribution_level' => $evaluation->distributionLevel ?? 0,
            'distribution_resolved_at' => $evaluation->distributionResolvedAt,
            'beneficiary_external_user_id' => $evaluation->beneficiaryExternalUserId,
            'subscription_id' => $evaluation->subscriptionId,
            'plan_id' => $evaluation->planId,
            'program_id' => $evaluation->programId,
            'occurred_at' => $evaluation->occurredAt,
            'window_starts_at' => $evaluation->window?->startsAt,
            'window_ends_at' => $evaluation->window?->endsAt,
            'metric_code' => $evaluation->metricCode,
            'unit_code' => $evaluation->unitCode,
            'instrument_reference' => $evaluation->instrumentReference,
            'quantity' => $evaluation->quantity->value(),
            'status' => $evaluation->status->value,
            'exclusion_reason' => $evaluation->exclusionReason?->value,
            'evaluated_at' => $evaluation->evaluatedAt,
            'created_at' => $evaluation->createdAt,
            'updated_at' => $evaluation->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function contributionAttributes(Contribution $contribution): array
    {
        return [
            'id' => $contribution->id,
            'evaluation_id' => $contribution->evaluationId,
            'rule_id' => $contribution->ruleId,
            'rule_version_id' => $contribution->ruleVersionId,
            'rule_assignment_id' => $contribution->ruleAssignmentId,
            'program_symbol_configuration_id' => $contribution->programSymbolConfigurationId,
            'plan_progression_template_version_binding_id' => $contribution->planProgressionTemplateVersionBindingId,
            'progression_template_version_id' => $contribution->progressionTemplateVersionId,
            'strategy_type' => $contribution->strategyType->value,
            'scope_type' => $contribution->scopeType->value,
            'weight' => $contribution->weight->value(),
            'distribution_weight' => $contribution->distributionWeight->value(),
            'points' => $contribution->points->value(),
            'created_at' => $contribution->createdAt,
            'updated_at' => $contribution->updatedAt,
        ];
    }
}
