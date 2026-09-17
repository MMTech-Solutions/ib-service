<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Support;

use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;

final class ProgressionActivityCursor
{
    public static function encode(string $occurredAt, string $sourceActivityId): string
    {
        return rtrim(strtr(base64_encode($occurredAt.'|'.$sourceActivityId), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function decode(string $cursor): array
    {
        $padded = strtr($cursor, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false || ! str_contains($decoded, '|')) {
            throw InvalidProgressionActivityQueryException::withMessage('Activity cursor is invalid.');
        }

        [$occurredAt, $sourceActivityId] = explode('|', $decoded, 2);
        if ($occurredAt === '' || $sourceActivityId === '') {
            throw InvalidProgressionActivityQueryException::withMessage('Activity cursor is invalid.');
        }

        return [$occurredAt, $sourceActivityId];
    }
}
