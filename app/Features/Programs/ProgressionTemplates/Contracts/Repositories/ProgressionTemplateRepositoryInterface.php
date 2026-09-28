<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Contracts\Repositories;

use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;
use Closure;

interface ProgressionTemplateRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return list<ProgressionTemplate> */
    public function all(): array;

    public function find(string $id): ?ProgressionTemplate;

    public function save(ProgressionTemplate $template, ?int $expectedLockVersion = null): void;

    public function delete(ProgressionTemplate $template, int $expectedLockVersion): void;

    public function saveVersion(ProgressionTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void;

    public function deleteVersion(ProgressionTemplateVersion $version, int $expectedLockVersion): void;
}
