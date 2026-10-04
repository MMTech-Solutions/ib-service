<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class TemplateBindingException extends ApiException
{
    public static function archived(string $id): self
    {
        return new self('PLAN_ARCHIVED', "Plan [{$id}] is archived.", 409);
    }

    public static function versionNotFound(string $id): self
    {
        return new self('TEMPLATE_VERSION_NOT_FOUND', "Template version [{$id}] was not found in this catalog.", 404);
    }

    public static function unpublished(string $id): self
    {
        return new self('TEMPLATE_VERSION_NOT_PUBLISHED', "Template version [{$id}] must be published.", 409);
    }

    public static function bindingNotFound(string $id): self
    {
        return new self('TEMPLATE_BINDING_NOT_FOUND', "Template binding [{$id}] was not found in this plan.", 404);
    }
}
