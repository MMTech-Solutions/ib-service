<?php

declare(strict_types=1);

namespace App\Features\Settings\Factories;

use App\Features\Settings\Repositories\SettingRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class SettingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): SettingRepositoryInterface
    {
        return $this->container->make(SettingRepositoryInterface::class);
    }
}
