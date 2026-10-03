<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Factories;

use App\Features\Plans\Catalog\Contracts\Repositories\PaymentTemplateBindingRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class PaymentTemplateBindingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): PaymentTemplateBindingRepositoryInterface
    {
        return $this->container->make('plans.payment-template-bindings.repositories.postgresql');
    }
}
