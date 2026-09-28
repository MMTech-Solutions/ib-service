<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Repositories\InMemory;

use App\Features\Programs\ProgressionTemplates\Contracts\Repositories\ProgressionTemplateRepositoryInterface;
use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;
use Closure;
use Throwable;

final class InMemoryProgressionTemplateRepository implements ProgressionTemplateRepositoryInterface
{
    /** @var array<string, ProgressionTemplate> */
    private array $templates = [];

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->templates), ['allowed_classes' => true]);
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->templates = $snapshot;
            throw $exception;
        }
    }

    public function all(): array
    {
        $items = array_map(fn (ProgressionTemplate $item): ProgressionTemplate => $this->copy($item), array_values($this->templates));
        usort($items, fn (ProgressionTemplate $a, ProgressionTemplate $b): int => [$a->name, $a->id] <=> [$b->name, $b->id]);

        return $items;
    }

    public function find(string $id): ?ProgressionTemplate
    {
        return isset($this->templates[$id]) ? $this->copy($this->templates[$id]) : null;
    }

    public function save(ProgressionTemplate $template, ?int $expectedLockVersion = null): void
    {
        $stored = $this->templates[$template->id] ?? null;
        if ($stored !== null && $expectedLockVersion !== null && $stored->lockVersion !== $expectedLockVersion) {
            throw ProgressionTemplateException::concurrency($template->id);
        } foreach ($this->templates as $current) {
            if ($current->id !== $template->id && $current->name === $template->name) {
                throw ProgressionTemplateException::duplicate($template->name);
            }
        } if ($stored !== null && $expectedLockVersion !== null) {
            $template->lockVersion++;
        } $this->templates[$template->id] = $this->copy($template);
    }

    public function delete(ProgressionTemplate $template, int $expectedLockVersion): void
    {
        $stored = $this->templates[$template->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw ProgressionTemplateException::concurrency($template->id);
        } unset($this->templates[$template->id]);
    }

    public function saveVersion(ProgressionTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void
    {
        foreach ($this->templates as $template) {
            foreach ($template->versions as $index => $stored) {
                if ($stored->id === $version->id && ! $creating) {
                    if ($stored->lockVersion !== $expectedLockVersion) {
                        throw ProgressionTemplateException::concurrency($version->id);
                    } $version->lockVersion++;
                    $template->versions[$index] = $version;

                    return;
                }
            } if ($template->id === $version->templateId && $creating) {
                $template->versions[] = $version;

                return;
            }
        } throw ProgressionTemplateException::versionNotFound($version->id);
    }

    public function deleteVersion(ProgressionTemplateVersion $version, int $expectedLockVersion): void
    {
        foreach ($this->templates as $template) {
            foreach ($template->versions as $index => $stored) {
                if ($stored->id === $version->id) {
                    if ($stored->status !== 'draft') {
                        throw ProgressionTemplateException::immutable($version->id);
                    }
                    if ($stored->lockVersion !== $expectedLockVersion) {
                        throw ProgressionTemplateException::concurrency($version->id);
                    } array_splice($template->versions, $index, 1);

                    return;
                }
            }
        } throw ProgressionTemplateException::versionNotFound($version->id);
    }

    private function copy(ProgressionTemplate $template): ProgressionTemplate
    {
        return unserialize(serialize($template), ['allowed_classes' => true]);
    }
}
