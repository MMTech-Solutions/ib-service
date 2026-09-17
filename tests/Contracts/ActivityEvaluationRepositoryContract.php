<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class ActivityEvaluationRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): ActivityEvaluationRepositoryInterface;

    abstract protected function moduleId(): string;

    abstract protected function subscriptionId(): string;

    abstract protected function planId(): string;

    abstract protected function programId(): string;

    abstract protected function ruleId(): string;

    abstract protected function ruleVersionId(): string;

    abstract protected function ruleAssignmentId(): string;

    public function test_it_records_an_accepted_evaluation_with_contribution_atomically(): void
    {
        $repository = $this->repository();
        $evaluation = $this->acceptedEvaluation();

        $stored = $repository->record($evaluation);

        self::assertTrue($stored->isAccepted());
        self::assertNotNull($stored->contribution);
        self::assertSame('10', $stored->contribution->points->value());
        self::assertSame($evaluation->id, $stored->id);

        $byKey = $repository->findByIdempotencyKey(
            $evaluation->moduleId,
            $evaluation->sourceActivityId,
            $evaluation->beneficiaryExternalUserId,
        );
        self::assertNotNull($byKey);
        self::assertSame($stored->id, $byKey->id);
        self::assertNotNull($byKey->contribution);
    }

    public function test_it_records_each_exclusion_reason_without_contribution(): void
    {
        $repository = $this->repository();

        foreach (ExclusionReason::cases() as $index => $reason) {
            $evaluation = $this->excludedEvaluation(
                sourceActivityId: "source-{$reason->value}-{$index}",
                reason: $reason,
            );

            $stored = $repository->record($evaluation);

            self::assertTrue($stored->isExcluded());
            self::assertSame($reason, $stored->exclusionReason);
            self::assertNull($stored->contribution);
        }
    }

    public function test_it_returns_the_canonical_evaluation_on_idempotent_retry(): void
    {
        $repository = $this->repository();
        $beneficiaryId = (string) Str::uuid7();
        $first = $this->acceptedEvaluation(
            sourceActivityId: 'idempotent-source',
            beneficiaryExternalUserId: $beneficiaryId,
        );
        $canonical = $repository->record($first);

        $retry = $this->acceptedEvaluation(
            id: (string) Str::uuid7(),
            sourceActivityId: 'idempotent-source',
            beneficiaryExternalUserId: $beneficiaryId,
            quantity: ExactDecimal::fromString('999'),
            weight: ExactDecimal::fromString('2'),
        );
        $returned = $repository->record($retry);

        self::assertSame($canonical->id, $returned->id);
        self::assertSame('10', $returned->contribution?->points->value());
        self::assertNotSame('1998', $returned->contribution?->points->value());
    }

    public function test_it_rolls_back_the_outer_transaction_without_leaving_partial_state(): void
    {
        $repository = $this->repository();
        $evaluation = $this->acceptedEvaluation(sourceActivityId: 'rollback-source');

        try {
            $repository->transaction(function () use ($repository, $evaluation): void {
                $repository->record($evaluation);
                throw new RuntimeException('force rollback');
            });
            self::fail('Expected the outer transaction to fail.');
        } catch (RuntimeException) {
        }

        self::assertNull($repository->findById($evaluation->id));
        self::assertNull($repository->findByIdempotencyKey(
            $evaluation->moduleId,
            $evaluation->sourceActivityId,
            $evaluation->beneficiaryExternalUserId,
        ));
    }

    protected function acceptedEvaluation(
        ?string $id = null,
        ?string $sourceActivityId = null,
        ?string $beneficiaryExternalUserId = null,
        ?ExactDecimal $quantity = null,
        ?ExactDecimal $weight = null,
    ): ActivityEvaluation {
        $evaluationId = $id ?? (string) Str::uuid7();
        $quantity ??= ExactDecimal::fromString('100');
        $weight ??= ExactDecimal::fromString('0.1');
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-17T00:00:00Z'),
            CarbonImmutable::parse('2026-09-18T00:00:00Z'),
        );

        $contribution = Contribution::create(
            id: (string) Str::uuid7(),
            evaluationId: $evaluationId,
            ruleId: $this->ruleId(),
            ruleVersionId: $this->ruleVersionId(),
            ruleAssignmentId: $this->ruleAssignmentId(),
            quantity: $quantity,
            weight: $weight,
            now: $now,
        );

        return ActivityEvaluation::accepted([
            'id' => $evaluationId,
            'moduleId' => $this->moduleId(),
            'sourceActivityId' => $sourceActivityId ?? 'source-accepted',
            'beneficiaryExternalUserId' => $beneficiaryExternalUserId ?? (string) Str::uuid7(),
            'subscriptionId' => $this->subscriptionId(),
            'planId' => $this->planId(),
            'programId' => $this->programId(),
            'occurredAt' => $now,
            'window' => $window,
            'metricCode' => 'confirmed_deposit',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => $quantity,
            'evaluatedAt' => $now,
            'contribution' => $contribution,
        ]);
    }

    protected function excludedEvaluation(
        string $sourceActivityId,
        ExclusionReason $reason,
    ): ActivityEvaluation {
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');

        return ActivityEvaluation::excluded([
            'id' => (string) Str::uuid7(),
            'moduleId' => $this->moduleId(),
            'sourceActivityId' => $sourceActivityId,
            'beneficiaryExternalUserId' => (string) Str::uuid7(),
            'subscriptionId' => null,
            'planId' => null,
            'programId' => null,
            'occurredAt' => $now,
            'window' => null,
            'metricCode' => 'confirmed_deposit',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => ExactDecimal::fromString('25'),
            'exclusionReason' => $reason,
            'evaluatedAt' => $now,
        ]);
    }
}
