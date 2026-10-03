<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Repositories\InMemory;

use App\Features\Programs\PaymentTemplates\Contracts\Repositories\PaymentTemplateRepositoryInterface;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use Closure;
use Throwable;

final class InMemoryPaymentTemplateRepository implements PaymentTemplateRepositoryInterface
{
    /** @var array<string, PaymentTemplate> */
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
        $items = array_map(fn (PaymentTemplate $item): PaymentTemplate => $this->copy($item), array_values($this->templates));
        usort($items, fn (PaymentTemplate $a, PaymentTemplate $b): int => [$a->name, $a->id] <=> [$b->name, $b->id]);

        return $items;
    }

    public function find(string $id): ?PaymentTemplate
    {
        return isset($this->templates[$id]) ? $this->copy($this->templates[$id]) : null;
    }

    public function findVersion(string $versionId): ?PaymentTemplateVersion
    {
        foreach ($this->all() as $template) {
            foreach ($template->versions as $version) {
                if ($version->id === $versionId) {
                    return $version;
                }
            }
        }

        return null;
    }

    public function save(PaymentTemplate $template, ?int $expectedLockVersion = null): void
    {
        $stored = $this->templates[$template->id] ?? null;
        if ($stored !== null && $expectedLockVersion !== null && $stored->lockVersion !== $expectedLockVersion) {
            throw PaymentTemplateException::concurrency($template->id);
        } foreach ($this->templates as $current) {
            if ($current->id !== $template->id && $current->name === $template->name) {
                throw PaymentTemplateException::duplicate($template->name);
            }
        } if ($stored !== null && $expectedLockVersion !== null) {
            $template->lockVersion++;
        } $this->templates[$template->id] = $this->copy($template);
    }

    public function delete(PaymentTemplate $template, int $expectedLockVersion): void
    {
        $stored = $this->templates[$template->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw PaymentTemplateException::concurrency($template->id);
        } unset($this->templates[$template->id]);
    }

    public function saveVersion(PaymentTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void
    {
        foreach ($this->templates as $template) {
            foreach ($template->versions as $index => $stored) {
                if ($stored->id === $version->id && ! $creating) {
                    if ($stored->lockVersion !== $expectedLockVersion) {
                        throw PaymentTemplateException::concurrency($version->id);
                    } $version->lockVersion++;
                    $template->versions[$index] = $version;

                    return;
                }
            } if ($template->id === $version->templateId && $creating) {
                $template->versions[] = $version;

                return;
            }
        } throw PaymentTemplateException::versionNotFound($version->id);
    }

    public function deleteVersion(PaymentTemplateVersion $version, int $expectedLockVersion): void
    {
        foreach ($this->templates as $template) {
            foreach ($template->versions as $index => $stored) {
                if ($stored->id === $version->id) {
                    if ($stored->status !== 'draft') {
                        throw PaymentTemplateException::immutable($version->id);
                    }
                    if ($stored->lockVersion !== $expectedLockVersion) {
                        throw PaymentTemplateException::concurrency($version->id);
                    } array_splice($template->versions, $index, 1);

                    return;
                }
            }
        } throw PaymentTemplateException::versionNotFound($version->id);
    }

    private function copy(PaymentTemplate $template): PaymentTemplate
    {
        return unserialize(serialize($template), ['allowed_classes' => true]);
    }
}
