<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Models;

use App\Features\Rules\Catalog\DTOs\RuleVersionData;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Exceptions\RuleVersionImmutableException;

final class RuleVersion
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ruleId,
        public readonly int $versionNumber,
        public RuleVersionStatus $status,
        public int $schemaVersion,
        public array $configuration,
        public ?string $publishedAt,
        public int $lockVersion,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function draft(
        string $id,
        string $ruleId,
        int $versionNumber,
        int $schemaVersion,
        array $configuration,
        string $now,
    ): self {
        return new self(
            id: $id,
            ruleId: $ruleId,
            versionNumber: $versionNumber,
            status: RuleVersionStatus::Draft,
            schemaVersion: $schemaVersion,
            configuration: $configuration,
            publishedAt: null,
            lockVersion: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function replaceConfiguration(int $schemaVersion, array $configuration, string $now): bool
    {
        $this->assertDraft();

        if ($this->schemaVersion === $schemaVersion && $this->configuration === $configuration) {
            return false;
        }

        $this->schemaVersion = $schemaVersion;
        $this->configuration = $configuration;
        $this->updatedAt = $now;

        return true;
    }

    public function publish(string $now): void
    {
        $this->assertDraft();
        $this->status = RuleVersionStatus::Published;
        $this->publishedAt = $now;
        $this->updatedAt = $now;
    }

    public function isDraft(): bool
    {
        return $this->status === RuleVersionStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === RuleVersionStatus::Published;
    }

    public function toData(): RuleVersionData
    {
        return new RuleVersionData(
            id: $this->id,
            rule_id: $this->ruleId,
            version_number: $this->versionNumber,
            status: $this->status->value,
            schema_version: $this->schemaVersion,
            configuration: $this->configuration,
            published_at: $this->publishedAt,
            lock_version: $this->lockVersion,
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }

    private function assertDraft(): void
    {
        if (! $this->isDraft()) {
            throw RuleVersionImmutableException::forId($this->id);
        }
    }
}
