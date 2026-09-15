<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Models;

use App\Features\Rules\Catalog\DTOs\RuleData;
use App\Features\Rules\Catalog\DTOs\RuleDetailData;
use App\Features\Rules\Catalog\Support\RuleSlug;

final class Rule
{
    /**
     * @param  list<RuleVersion>  $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public string $name,
        public readonly string $slug,
        public ?string $description,
        public readonly string $strategyType,
        public int $lockVersion,
        public array $versions,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    public static function create(
        string $id,
        string $planId,
        string $name,
        ?string $description,
        string $strategyType,
        string $now,
    ): self {
        return new self(
            id: $id,
            planId: $planId,
            name: $name,
            slug: RuleSlug::fromName($name),
            description: $description,
            strategyType: $strategyType,
            lockVersion: 1,
            versions: [],
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function updateAdministrativeFields(string $name, ?string $description, string $now): bool
    {
        if ($this->name === $name && $this->description === $description) {
            return false;
        }

        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;

        return true;
    }

    public function findVersion(string $versionId): ?RuleVersion
    {
        foreach ($this->versions as $version) {
            if ($version->id === $versionId) {
                return $version;
            }
        }

        return null;
    }

    public function addVersion(RuleVersion $version, string $now): void
    {
        $this->versions[] = $version;
        $this->updatedAt = $now;
        usort(
            $this->versions,
            static fn (RuleVersion $a, RuleVersion $b): int => $a->versionNumber <=> $b->versionNumber,
        );
    }

    public function toData(): RuleData
    {
        return new RuleData(
            id: $this->id,
            plan_id: $this->planId,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            strategy_type: $this->strategyType,
            lock_version: $this->lockVersion,
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }

    public function toDetailData(): RuleDetailData
    {
        $list = $this->toData();

        return new RuleDetailData(
            id: $list->id,
            plan_id: $list->plan_id,
            name: $list->name,
            slug: $list->slug,
            description: $list->description,
            strategy_type: $list->strategy_type,
            lock_version: $list->lock_version,
            versions: array_map(
                static fn (RuleVersion $version) => $version->toData(),
                $this->versions,
            ),
            created_at: $list->created_at,
            updated_at: $list->updated_at,
        );
    }
}
