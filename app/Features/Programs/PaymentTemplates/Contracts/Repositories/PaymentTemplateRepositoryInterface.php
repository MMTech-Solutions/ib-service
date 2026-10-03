<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Contracts\Repositories;

use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use Closure;

interface PaymentTemplateRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return list<PaymentTemplate> */
    public function all(): array;

    public function find(string $id): ?PaymentTemplate;

    public function findVersion(string $versionId): ?PaymentTemplateVersion;

    public function save(PaymentTemplate $template, ?int $expectedLockVersion = null): void;

    public function delete(PaymentTemplate $template, int $expectedLockVersion): void;

    public function saveVersion(PaymentTemplateVersion $version, bool $creating, ?int $expectedLockVersion = null): void;

    public function deleteVersion(PaymentTemplateVersion $version, int $expectedLockVersion): void;
}
