<?php

declare(strict_types=1);

namespace App\Features\Settings\Exceptions;

use App\Support\Exceptions\ApiException;

final class SettingException extends ApiException
{
    public static function unknown(string $key): self
    {
        return new self('SETTING_NOT_FOUND', "Setting [{$key}] is not registered.", 404);
    }

    public static function unsynchronized(string $key): self
    {
        return new self('SETTING_NOT_SYNCHRONIZED', "Run settings:sync before modifying [{$key}].", 409);
    }

    public static function conflict(): self
    {
        return new self('SETTING_CONFLICT', 'The setting changed. Reload before retrying.', 409);
    }

    public static function invalid(string $key): self
    {
        return new self('SETTING_INVALID', "The value or definition for [{$key}] is invalid.", 422);
    }
}
