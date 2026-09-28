<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Exceptions;

use App\Support\Exceptions\ApiException;

final class PaymentTemplateException extends ApiException
{
    public static function notFound(string $id): self
    {
        return new self('PAYMENT_TEMPLATE_NOT_FOUND', "Payment template [{$id}] was not found.", 404);
    }

    public static function versionNotFound(string $id): self
    {
        return new self('PAYMENT_TEMPLATE_VERSION_NOT_FOUND', "Payment template version [{$id}] was not found.", 404);
    }

    public static function duplicate(string $name): self
    {
        return new self('PAYMENT_TEMPLATE_NAME_CONFLICT', "Payment template name [{$name}] already exists.", 409);
    }

    public static function concurrency(string $id): self
    {
        return new self('PAYMENT_TEMPLATE_CONCURRENCY_CONFLICT', "Payment template resource [{$id}] was modified by another operation.", 409);
    }

    public static function immutable(string $id): self
    {
        return new self('PAYMENT_TEMPLATE_VERSION_IMMUTABLE', "Payment template version [{$id}] is published and immutable.", 409);
    }
}
