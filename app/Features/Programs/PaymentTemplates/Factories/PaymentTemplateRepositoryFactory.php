<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Factories;

use App\Features\Programs\PaymentTemplates\Contracts\Repositories\PaymentTemplateRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class PaymentTemplateRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): PaymentTemplateRepositoryInterface
    {
        return match ($driver ?? config('payment-templates.repository', 'postgresql')) {
            'memory' => $this->container->make('programs.payment-templates.repositories.memory'),
            'postgresql' => $this->container->make('programs.payment-templates.repositories.postgresql'),
            default => throw new InvalidArgumentException('Unsupported payment templates repository.'),
        };
    }
}
