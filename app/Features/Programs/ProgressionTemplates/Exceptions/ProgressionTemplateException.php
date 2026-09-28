<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgressionTemplateException extends ApiException
{
    public static function notFound(string $id): self
    {
        return new self('PROGRESSION_TEMPLATE_NOT_FOUND', "Progression template [{$id}] was not found.", 404);
    }

    public static function versionNotFound(string $id): self
    {
        return new self('PROGRESSION_TEMPLATE_VERSION_NOT_FOUND', "Progression template version [{$id}] was not found.", 404);
    }

    public static function duplicate(string $name): self
    {
        return new self('PROGRESSION_TEMPLATE_NAME_CONFLICT', "Progression template name [{$name}] already exists.", 409);
    }

    public static function concurrency(string $id): self
    {
        return new self('PROGRESSION_TEMPLATE_CONCURRENCY_CONFLICT', "Progression template resource [{$id}] was modified by another operation.", 409);
    }

    public static function immutable(string $id): self
    {
        return new self('PROGRESSION_TEMPLATE_VERSION_IMMUTABLE', "Progression template version [{$id}] is published and immutable.", 409);
    }
}
