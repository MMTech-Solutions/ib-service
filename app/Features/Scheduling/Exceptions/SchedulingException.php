<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Exceptions;

use App\Support\Exceptions\ApiException;

final class SchedulingException extends ApiException
{
    public static function missing(): self
    {
        return new self('SCHEDULING_NOT_FOUND', 'Scheduling resource not found.', 404);
    }

    public static function conflict(string $message): self
    {
        return new self('SCHEDULING_CONFLICT', $message, 409);
    }
}
