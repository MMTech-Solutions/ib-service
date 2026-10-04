<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Repositories\PostgreSql;

use App\Features\Programs\ProgressionTemplates\Contracts\Repositories\ProgressionTemplateRepositoryInterface;
use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateLevel;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlProgressionTemplateRepository implements ProgressionTemplateRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function all(): array
    {
        return $this->connection->table('progression_templates')->orderBy('name')->orderBy('id')->get()->map(fn ($row): ProgressionTemplate => $this->hydrate($row))->all();
    }

    public function find(string $id): ?ProgressionTemplate
    {
        $row = $this->connection->table('progression_templates')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findVersion(string $versionId): ?ProgressionTemplateVersion
    {
        $row = $this->connection->table('progression_template_versions')->where('id', $versionId)->first();
        if ($row === null) {
            return null;
        }
        $levels = $this->connection->table('progression_template_levels')->where('template_version_id', $versionId)->orderBy('distribution_level')->get()->map(fn ($level): ProgressionTemplateLevel => new ProgressionTemplateLevel((string) $level->id, (int) $level->distribution_level, (string) $level->weight))->all();

        return new ProgressionTemplateVersion((string) $row->id, (string) $row->template_id, (int) $row->version_number, (string) $row->status, $row->published_at === null ? null : (string) $row->published_at, (int) $row->lock_version, $levels, (string) $row->created_at, (string) $row->updated_at);
    }

    public function save(ProgressionTemplate $template, ?int $expectedLockVersion = null): void
    {
        try {
            if ($expectedLockVersion === null) {
                $this->connection->table('progression_templates')->insert($this->attributes($template));

                return;
            }
            $affected = $this->connection->table('progression_templates')->where('id', $template->id)->where('lock_version', $expectedLockVersion)->update(['name' => $template->name, 'description' => $template->description, 'lock_version' => $expectedLockVersion + 1, 'updated_at' => $template->updatedAt]);
            if ($affected !== 1) {
                throw ProgressionTemplateException::concurrency($template->id);
            }
            $template->lockVersion = $expectedLockVersion + 1;
        } catch (UniqueConstraintViolationException) {
            throw ProgressionTemplateException::duplicate($template->name);
        }
    }

    public function delete(ProgressionTemplate $template, int $expectedLockVersion): void
    {
        if ($this->connection->table('progression_templates')->where('id', $template->id)->where('lock_version', $expectedLockVersion)->delete() !== 1) {
            throw ProgressionTemplateException::concurrency($template->id);
        }
    }

    public function saveVersion(ProgressionTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void
    {
        if ($creating) {
            $this->connection->table('progression_template_versions')->insert(['id' => $version->id, 'template_id' => $version->templateId, 'version_number' => $version->versionNumber, 'status' => $version->status, 'published_at' => $version->publishedAt, 'lock_version' => $version->lockVersion, 'created_at' => $version->createdAt, 'updated_at' => $version->updatedAt]);
            $this->replaceLevels($version);

            return;
        } $affected = $this->connection->table('progression_template_versions')->where('id', $version->id)->where('status', 'draft')->where('lock_version', $expectedLockVersion)->update(['status' => $version->status, 'published_at' => $version->publishedAt, 'lock_version' => $expectedLockVersion + 1, 'updated_at' => $version->updatedAt]);
        if ($affected !== 1) {
            $current = $this->connection->table('progression_template_versions')->where('id', $version->id)->first();
            throw $current !== null && $current->status === 'published' ? ProgressionTemplateException::immutable($version->id) : ProgressionTemplateException::concurrency($version->id);
        } $version->lockVersion = $expectedLockVersion + 1;
        $this->replaceLevels($version);
    }

    public function deleteVersion(ProgressionTemplateVersion $version, int $expectedLockVersion): void
    {
        $affected = $this->connection->table('progression_template_versions')->where('id', $version->id)->where('status', 'draft')->where('lock_version', $expectedLockVersion)->delete();
        if ($affected !== 1) {
            $current = $this->connection->table('progression_template_versions')->where('id', $version->id)->first();
            throw $current !== null && $current->status === 'published' ? ProgressionTemplateException::immutable($version->id) : ProgressionTemplateException::concurrency($version->id);
        }
    }

    private function replaceLevels(ProgressionTemplateVersion $version): void
    {
        $this->connection->table('progression_template_levels')->where('template_version_id', $version->id)->delete();
        foreach ($version->levels as $level) {
            $this->connection->table('progression_template_levels')->insert(['id' => $level->id, 'template_version_id' => $version->id, 'distribution_level' => $level->distributionLevel, 'weight' => $level->weight]);
        }
    }

    private function hydrate(object $row): ProgressionTemplate
    {
        $versions = $this->connection->table('progression_template_versions')->where('template_id', $row->id)->orderBy('version_number')->get()->map(function ($version): ProgressionTemplateVersion {
            $levels = $this->connection->table('progression_template_levels')->where('template_version_id', $version->id)->orderBy('distribution_level')->get()->map(fn ($level): ProgressionTemplateLevel => new ProgressionTemplateLevel((string) $level->id, (int) $level->distribution_level, (string) $level->weight))->all();

            return new ProgressionTemplateVersion((string) $version->id, (string) $version->template_id, (int) $version->version_number, (string) $version->status, $version->published_at === null ? null : (string) $version->published_at, (int) $version->lock_version, $levels, (string) $version->created_at, (string) $version->updated_at);
        })->all();

        return new ProgressionTemplate((string) $row->id, (string) $row->name, $row->description === null ? null : (string) $row->description, (int) $row->lock_version, $versions, (string) $row->created_at, (string) $row->updated_at);
    }

    /** @return array<string, mixed> */
    private function attributes(ProgressionTemplate $template): array
    {
        return ['id' => $template->id, 'name' => $template->name, 'description' => $template->description, 'lock_version' => $template->lockVersion, 'created_at' => $template->createdAt, 'updated_at' => $template->updatedAt];
    }
}
