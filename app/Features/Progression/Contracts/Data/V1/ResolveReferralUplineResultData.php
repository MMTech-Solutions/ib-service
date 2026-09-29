<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveReferralUplineResultData extends Data
{
    /** @param list<ReferralUplineBeneficiaryData> $beneficiaries */
    public function __construct(
        public readonly bool $resolved,
        public readonly array $beneficiaries = [],
        public readonly ?string $resolved_at = null,
        public readonly ?string $failure_code = null,
    ) {}

    /** @param list<ReferralUplineBeneficiaryData> $beneficiaries */
    public static function resolved(array $beneficiaries, string $resolvedAt): self
    {
        return new self(true, $beneficiaries, $resolvedAt);
    }

    public static function failed(string $failureCode): self
    {
        return new self(false, failure_code: $failureCode);
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
