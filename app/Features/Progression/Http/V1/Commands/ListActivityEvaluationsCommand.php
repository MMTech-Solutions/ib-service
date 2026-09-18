<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Commands;

use App\Features\Progression\DTOs\ActivityEvaluationListQueryData;
use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Http\V1\Requests\ListActivityEvaluationsRequest;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ListActivityEvaluationsCommand extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $planId,
        public readonly ?string $subscriptionId,
        public readonly ?EvaluationStatus $status,
        public readonly ?ExclusionReason $exclusionReason,
        public readonly ?CarbonImmutable $occurredAtFrom,
        public readonly ?CarbonImmutable $occurredAtTo,
    ) {}

    public static function fromRequest(ListActivityEvaluationsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            planId: isset($validated['plan_id']) ? (string) $validated['plan_id'] : null,
            subscriptionId: isset($validated['subscription_id'])
                ? (string) $validated['subscription_id']
                : null,
            status: isset($validated['status'])
                ? EvaluationStatus::from((string) $validated['status'])
                : null,
            exclusionReason: isset($validated['exclusion_reason'])
                ? ExclusionReason::from((string) $validated['exclusion_reason'])
                : null,
            occurredAtFrom: isset($validated['occurred_at_from'])
                ? CarbonImmutable::parse((string) $validated['occurred_at_from'])->utc()
                : null,
            occurredAtTo: isset($validated['occurred_at_to'])
                ? CarbonImmutable::parse((string) $validated['occurred_at_to'])->utc()
                : null,
        );
    }

    public function toQueryData(): ActivityEvaluationListQueryData
    {
        return new ActivityEvaluationListQueryData(
            page: $this->page,
            perPage: $this->perPage,
            planId: $this->planId,
            subscriptionId: $this->subscriptionId,
            status: $this->status,
            exclusionReason: $this->exclusionReason,
            occurredAtFrom: $this->occurredAtFrom,
            occurredAtTo: $this->occurredAtTo,
        );
    }
}
