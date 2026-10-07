<?php

declare(strict_types=1);

namespace App\Features\Scheduling\ValueObjects;

use Cron\CronExpression as Parser;
use InvalidArgumentException;

final class CronExpression
{
    public static function valid(string $expression): bool
    {
        return count(preg_split('/\\s+/', trim($expression)) ?: []) === 5 && Parser::isValidExpression($expression);
    }

    public static function assertValid(string $expression): void
    {
        if (! self::valid($expression)) {
            throw new InvalidArgumentException('A valid five-field cron expression is required.');
        }
    }
}
