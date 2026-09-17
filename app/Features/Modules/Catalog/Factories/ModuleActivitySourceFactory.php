<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Sources\Broker\Services\Adapters\FixtureBrokerDepositsActivityAdapter;
use App\Features\Modules\Sources\Contracts\ModuleActivitySourceInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ModuleActivitySourceFactory
{
    /**
     * Closed allowlist of capability codes that may be queried for Progression.
     *
     * @var array<string, class-string<ModuleActivitySourceInterface>>
     */
    private const ALLOWLIST = [
        'deposits' => FixtureBrokerDepositsActivityAdapter::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function makeForCapability(string $capabilityCode): ModuleActivitySourceInterface
    {
        $class = self::ALLOWLIST[$capabilityCode] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException(
                "Capability [{$capabilityCode}] is not registered for Progression activity queries.",
            );
        }

        return $this->container->make($class);
    }

    public function supports(string $capabilityCode): bool
    {
        return array_key_exists($capabilityCode, self::ALLOWLIST);
    }

    /**
     * @return list<string>
     */
    public function supportedCapabilityCodes(): array
    {
        return array_keys(self::ALLOWLIST);
    }
}
