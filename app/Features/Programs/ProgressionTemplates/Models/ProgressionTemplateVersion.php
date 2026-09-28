<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Models;

final class ProgressionTemplateVersion
{
    /** @param list<ProgressionTemplateLevel> $levels */
    public function __construct(public string $id, public string $templateId, public int $versionNumber, public string $status, public ?string $publishedAt, public int $lockVersion, public array $levels, public string $createdAt, public string $updatedAt) {}

    /** @param list<ProgressionTemplateLevel> $levels */
    public function replaceLevels(array $levels, string $now): void
    {
        $this->assertDraft();
        $this->levels = $levels;
        $this->updatedAt = $now;
    }

    public function publish(string $now): void
    {
        $this->assertDraft();
        $this->status = 'published';
        $this->publishedAt = $now;
        $this->updatedAt = $now;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    private function assertDraft(): void
    {
        if (! $this->isDraft()) {
            throw new \LogicException('Only draft template versions can be changed.');
        }
    }
}
