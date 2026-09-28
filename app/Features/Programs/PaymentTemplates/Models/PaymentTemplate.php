<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Models;

final class PaymentTemplate
{
    /** @param list<PaymentTemplateVersion> $versions */
    public function __construct(public string $id, public string $name, public ?string $description, public int $lockVersion, public array $versions, public string $createdAt, public string $updatedAt) {}

    public function updateDetails(?string $name, ?string $description, string $now): void
    {
        if ($name !== null) {
            $this->name = $name;
        }

        if ($description !== null) {
            $this->description = $description;
        }

        $this->updatedAt = $now;
    }

    public function nextVersionNumber(): int
    {
        return count($this->versions) + 1;
    }
}
