<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use RuntimeException;

final class CpaCaptureNotApplicableException extends RuntimeException
{
    public static function create(string $reason): self
    {
        return new self($reason);
    }
}
