<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class VolumeRewardActivityProcessingResultData extends Data
{
    public function __construct(
        public readonly int $created,
        public readonly int $skipped,
        public readonly ?string $retry_code,
    ) {}

    public static function completed(int $created, int $skipped): self
    {
        return new self($created, $skipped, null);
    }

    public static function retryable(string $code): self
    {
        return new self(0, 0, $code);
    }
}
