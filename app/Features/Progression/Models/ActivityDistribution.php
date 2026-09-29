<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ActivityDistribution
{
    /** @param list<DistributionBeneficiary> $beneficiaries */
    private function __construct(
        public readonly string $id, public readonly string $moduleId, public readonly string $sourceActivityId,
        public readonly string $sourceExternalUserId, public readonly CarbonImmutable $resolvedAt,
        public readonly array $beneficiaries, public readonly CarbonImmutable $createdAt, public readonly CarbonImmutable $updatedAt,
    ) {
        $keys = array_map(static fn (DistributionBeneficiary $beneficiary): string => $beneficiary->key(), $beneficiaries);
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Distribution beneficiaries must be unique by beneficiary and level.');
        }
    }

    /** @param list<DistributionBeneficiary> $beneficiaries */
    public static function resolve(string $id, string $moduleId, string $sourceActivityId, string $sourceExternalUserId, CarbonImmutable $resolvedAt, array $beneficiaries): self
    {
        $timestamp = $resolvedAt->utc();

        return new self($id, $moduleId, $sourceActivityId, $sourceExternalUserId, $timestamp, $beneficiaries, $timestamp, $timestamp);
    }

    /** @param list<DistributionBeneficiary> $beneficiaries */
    public static function reconstitute(string $id, string $moduleId, string $sourceActivityId, string $sourceExternalUserId, CarbonImmutable $resolvedAt, array $beneficiaries, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): self
    {
        return new self($id, $moduleId, $sourceActivityId, $sourceExternalUserId, $resolvedAt->utc(), $beneficiaries, $createdAt->utc(), $updatedAt->utc());
    }

    public function idempotencyKey(): string
    {
        return $this->moduleId.'|'.$this->sourceActivityId;
    }
}
