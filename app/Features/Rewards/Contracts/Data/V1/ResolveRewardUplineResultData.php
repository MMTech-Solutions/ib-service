<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveRewardUplineResultData extends Data
{
    /** @param list<RewardUplineBeneficiaryData> $beneficiaries */
    private function __construct(
        public readonly array $beneficiaries,
        public readonly ?string $resolved_at,
        public readonly ?string $failure_code,
    ) {}

    /** @param list<RewardUplineBeneficiaryData> $beneficiaries */
    public static function resolved(array $beneficiaries, string $resolvedAt): self
    {
        return new self($beneficiaries, $resolvedAt, null);
    }

    public static function failed(string $failureCode): self
    {
        return new self([], null, $failureCode);
    }

    public function isResolved(): bool
    {
        return $this->failure_code === null;
    }
}
