<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Repositories\PostgreSql;

use App\Features\Programs\PaymentTemplates\Contracts\Repositories\PaymentTemplateRepositoryInterface;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateLevel;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlPaymentTemplateRepository implements PaymentTemplateRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function all(): array
    {
        return $this->connection->table('payment_templates')->orderBy('name')->orderBy('id')->get()->map(fn ($row): PaymentTemplate => $this->hydrate($row))->all();
    }

    public function find(string $id): ?PaymentTemplate
    {
        $row = $this->connection->table('payment_templates')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findVersion(string $versionId): ?PaymentTemplateVersion
    {
        $row = $this->connection->table('payment_template_versions')->where('id', $versionId)->first();
        if ($row === null) {
            return null;
        }
        $levels = $this->connection->table('payment_template_levels')->where('template_version_id', $versionId)->orderBy('distribution_level')->get()->map(static fn ($level): PaymentTemplateLevel => new PaymentTemplateLevel((string) $level->id, (int) $level->distribution_level, (string) $level->rate))->all();

        return new PaymentTemplateVersion((string) $row->id, (string) $row->template_id, (int) $row->version_number, (string) $row->status, $row->published_at === null ? null : (string) $row->published_at, (int) $row->lock_version, $levels, (string) $row->created_at, (string) $row->updated_at);
    }

    public function save(PaymentTemplate $template, ?int $expectedLockVersion = null): void
    {
        try {
            if ($expectedLockVersion === null) {
                $this->connection->table('payment_templates')->insert($this->attributes($template));

                return;
            }
            $affected = $this->connection->table('payment_templates')->where('id', $template->id)->where('lock_version', $expectedLockVersion)->update(['name' => $template->name, 'description' => $template->description, 'lock_version' => $expectedLockVersion + 1, 'updated_at' => $template->updatedAt]);
            if ($affected !== 1) {
                throw PaymentTemplateException::concurrency($template->id);
            }
            $template->lockVersion = $expectedLockVersion + 1;
        } catch (UniqueConstraintViolationException) {
            throw PaymentTemplateException::duplicate($template->name);
        }
    }

    public function delete(PaymentTemplate $template, int $expectedLockVersion): void
    {
        if ($this->connection->table('payment_templates')->where('id', $template->id)->where('lock_version', $expectedLockVersion)->delete() !== 1) {
            throw PaymentTemplateException::concurrency($template->id);
        }
    }

    public function saveVersion(PaymentTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void
    {
        if ($creating) {
            $this->connection->table('payment_template_versions')->insert(['id' => $version->id, 'template_id' => $version->templateId, 'version_number' => $version->versionNumber, 'status' => $version->status, 'published_at' => $version->publishedAt, 'lock_version' => $version->lockVersion, 'created_at' => $version->createdAt, 'updated_at' => $version->updatedAt]);
            $this->replaceLevels($version);

            return;
        } $affected = $this->connection->table('payment_template_versions')->where('id', $version->id)->where('status', 'draft')->where('lock_version', $expectedLockVersion)->update(['status' => $version->status, 'published_at' => $version->publishedAt, 'lock_version' => $expectedLockVersion + 1, 'updated_at' => $version->updatedAt]);
        if ($affected !== 1) {
            $current = $this->connection->table('payment_template_versions')->where('id', $version->id)->first();
            throw $current !== null && $current->status === 'published' ? PaymentTemplateException::immutable($version->id) : PaymentTemplateException::concurrency($version->id);
        } $version->lockVersion = $expectedLockVersion + 1;
        $this->replaceLevels($version);
    }

    public function deleteVersion(PaymentTemplateVersion $version, int $expectedLockVersion): void
    {
        $affected = $this->connection->table('payment_template_versions')->where('id', $version->id)->where('status', 'draft')->where('lock_version', $expectedLockVersion)->delete();
        if ($affected !== 1) {
            $current = $this->connection->table('payment_template_versions')->where('id', $version->id)->first();
            throw $current !== null && $current->status === 'published' ? PaymentTemplateException::immutable($version->id) : PaymentTemplateException::concurrency($version->id);
        }
    }

    private function replaceLevels(PaymentTemplateVersion $version): void
    {
        $this->connection->table('payment_template_levels')->where('template_version_id', $version->id)->delete();
        foreach ($version->levels as $level) {
            $this->connection->table('payment_template_levels')->insert(['id' => $level->id, 'template_version_id' => $version->id, 'distribution_level' => $level->distributionLevel, 'rate' => $level->rate]);
        }
    }

    private function hydrate(object $row): PaymentTemplate
    {
        $versions = $this->connection->table('payment_template_versions')->where('template_id', $row->id)->orderBy('version_number')->get()->map(function ($version): PaymentTemplateVersion {
            $levels = $this->connection->table('payment_template_levels')->where('template_version_id', $version->id)->orderBy('distribution_level')->get()->map(fn ($level): PaymentTemplateLevel => new PaymentTemplateLevel((string) $level->id, (int) $level->distribution_level, (string) $level->rate))->all();

            return new PaymentTemplateVersion((string) $version->id, (string) $version->template_id, (int) $version->version_number, (string) $version->status, $version->published_at === null ? null : (string) $version->published_at, (int) $version->lock_version, $levels, (string) $version->created_at, (string) $version->updated_at);
        })->all();

        return new PaymentTemplate((string) $row->id, (string) $row->name, $row->description === null ? null : (string) $row->description, (int) $row->lock_version, $versions, (string) $row->created_at, (string) $row->updated_at);
    }

    /** @return array<string, mixed> */
    private function attributes(PaymentTemplate $template): array
    {
        return ['id' => $template->id, 'name' => $template->name, 'description' => $template->description, 'lock_version' => $template->lockVersion, 'created_at' => $template->createdAt, 'updated_at' => $template->updatedAt];
    }
}
