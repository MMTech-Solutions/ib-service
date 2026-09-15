<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\DTOs;

final class ProgramReorderItem
{
    public function __construct(
        public readonly string $id,
        public readonly int $entryThreshold,
        public readonly int $lockVersion,
    ) {}
}
