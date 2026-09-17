<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Exceptions\InvalidActivityEvaluationException;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;

final class ActivityEvaluation
{
    private function __construct(
        public readonly string $id,
        public readonly string $moduleId,
        public readonly string $sourceActivityId,
        public readonly string $beneficiaryExternalUserId,
        public readonly ?string $subscriptionId,
        public readonly ?string $planId,
        public readonly ?string $programId,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?ProgressionWindow $window,
        public readonly string $metricCode,
        public readonly string $unitCode,
        public readonly ?string $instrumentReference,
        public readonly ExactDecimal $quantity,
        public readonly EvaluationStatus $status,
        public readonly ?ExclusionReason $exclusionReason,
        public readonly CarbonImmutable $evaluatedAt,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
        public readonly ?Contribution $contribution,
    ) {}

    /**
     * @param  array{
     *     id: string,
     *     moduleId: string,
     *     sourceActivityId: string,
     *     beneficiaryExternalUserId: string,
     *     subscriptionId: string,
     *     planId: string,
     *     programId: string,
     *     occurredAt: CarbonImmutable,
     *     window: ProgressionWindow,
     *     metricCode: string,
     *     unitCode: string,
     *     instrumentReference: ?string,
     *     quantity: ExactDecimal,
     *     evaluatedAt: CarbonImmutable,
     *     contribution: Contribution,
     * }  $attributes
     */
    public static function accepted(array $attributes): self
    {
        $contribution = $attributes['contribution'];
        if ($contribution->evaluationId !== $attributes['id']) {
            throw InvalidActivityEvaluationException::forReason(
                'Contribution evaluation_id must match the accepted evaluation id.',
            );
        }

        $now = $attributes['evaluatedAt']->utc();

        $evaluation = new self(
            id: $attributes['id'],
            moduleId: $attributes['moduleId'],
            sourceActivityId: $attributes['sourceActivityId'],
            beneficiaryExternalUserId: $attributes['beneficiaryExternalUserId'],
            subscriptionId: $attributes['subscriptionId'],
            planId: $attributes['planId'],
            programId: $attributes['programId'],
            occurredAt: $attributes['occurredAt']->utc(),
            window: $attributes['window'],
            metricCode: $attributes['metricCode'],
            unitCode: $attributes['unitCode'],
            instrumentReference: $attributes['instrumentReference'],
            quantity: $attributes['quantity'],
            status: EvaluationStatus::Accepted,
            exclusionReason: null,
            evaluatedAt: $now,
            createdAt: $now,
            updatedAt: $now,
            contribution: $contribution,
        );
        $evaluation->assertInvariants();

        return $evaluation;
    }

    /**
     * @param  array{
     *     id: string,
     *     moduleId: string,
     *     sourceActivityId: string,
     *     beneficiaryExternalUserId: string,
     *     subscriptionId: ?string,
     *     planId: ?string,
     *     programId: ?string,
     *     occurredAt: CarbonImmutable,
     *     window: ?ProgressionWindow,
     *     metricCode: string,
     *     unitCode: string,
     *     instrumentReference: ?string,
     *     quantity: ExactDecimal,
     *     exclusionReason: ExclusionReason,
     *     evaluatedAt: CarbonImmutable,
     * }  $attributes
     */
    public static function excluded(array $attributes): self
    {
        $now = $attributes['evaluatedAt']->utc();

        $evaluation = new self(
            id: $attributes['id'],
            moduleId: $attributes['moduleId'],
            sourceActivityId: $attributes['sourceActivityId'],
            beneficiaryExternalUserId: $attributes['beneficiaryExternalUserId'],
            subscriptionId: $attributes['subscriptionId'],
            planId: $attributes['planId'],
            programId: $attributes['programId'],
            occurredAt: $attributes['occurredAt']->utc(),
            window: $attributes['window'],
            metricCode: $attributes['metricCode'],
            unitCode: $attributes['unitCode'],
            instrumentReference: $attributes['instrumentReference'],
            quantity: $attributes['quantity'],
            status: EvaluationStatus::Excluded,
            exclusionReason: $attributes['exclusionReason'],
            evaluatedAt: $now,
            createdAt: $now,
            updatedAt: $now,
            contribution: null,
        );
        $evaluation->assertInvariants();

        return $evaluation;
    }

    public static function reconstitute(
        string $id,
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
        ?string $subscriptionId,
        ?string $planId,
        ?string $programId,
        CarbonImmutable $occurredAt,
        ?ProgressionWindow $window,
        string $metricCode,
        string $unitCode,
        ?string $instrumentReference,
        ExactDecimal $quantity,
        EvaluationStatus $status,
        ?ExclusionReason $exclusionReason,
        CarbonImmutable $evaluatedAt,
        CarbonImmutable $createdAt,
        CarbonImmutable $updatedAt,
        ?Contribution $contribution,
    ): self {
        $evaluation = new self(
            id: $id,
            moduleId: $moduleId,
            sourceActivityId: $sourceActivityId,
            beneficiaryExternalUserId: $beneficiaryExternalUserId,
            subscriptionId: $subscriptionId,
            planId: $planId,
            programId: $programId,
            occurredAt: $occurredAt->utc(),
            window: $window,
            metricCode: $metricCode,
            unitCode: $unitCode,
            instrumentReference: $instrumentReference,
            quantity: $quantity,
            status: $status,
            exclusionReason: $exclusionReason,
            evaluatedAt: $evaluatedAt->utc(),
            createdAt: $createdAt->utc(),
            updatedAt: $updatedAt->utc(),
            contribution: $contribution,
        );
        $evaluation->assertInvariants();

        return $evaluation;
    }

    public function isAccepted(): bool
    {
        return $this->status === EvaluationStatus::Accepted;
    }

    public function isExcluded(): bool
    {
        return $this->status === EvaluationStatus::Excluded;
    }

    public function idempotencyKey(): string
    {
        return implode('|', [
            $this->moduleId,
            $this->sourceActivityId,
            $this->beneficiaryExternalUserId,
        ]);
    }

    private function assertInvariants(): void
    {
        if ($this->status === EvaluationStatus::Accepted) {
            if ($this->exclusionReason !== null) {
                throw InvalidActivityEvaluationException::forReason(
                    'Accepted evaluations cannot carry an exclusion_reason.',
                );
            }

            if (
                $this->subscriptionId === null
                || $this->planId === null
                || $this->programId === null
                || $this->window === null
            ) {
                throw InvalidActivityEvaluationException::forReason(
                    'Accepted evaluations require subscription, plan, program and window.',
                );
            }

            if ($this->contribution === null) {
                throw InvalidActivityEvaluationException::forReason(
                    'Accepted evaluations require a contribution.',
                );
            }

            if ($this->contribution->evaluationId !== $this->id) {
                throw InvalidActivityEvaluationException::forReason(
                    'Contribution evaluation_id must match the evaluation id.',
                );
            }

            return;
        }

        if ($this->exclusionReason === null) {
            throw InvalidActivityEvaluationException::forReason(
                'Excluded evaluations require an exclusion_reason.',
            );
        }

        if ($this->contribution !== null) {
            throw InvalidActivityEvaluationException::forReason(
                'Excluded evaluations cannot carry a contribution.',
            );
        }
    }
}
