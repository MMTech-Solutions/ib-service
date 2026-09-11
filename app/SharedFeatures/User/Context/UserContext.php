<?php

declare(strict_types=1);

namespace App\SharedFeatures\User\Context;

use App\SharedFeatures\User\Context\Contracts\UserConnectorInterface;
use BackedEnum;

final readonly class UserContext
{
    public function __construct(private UserConnectorInterface $connector) {}

    public function id(): string
    {
        return $this->connector->id();
    }

    /** @param BackedEnum|list<BackedEnum|string>|string $abilities */
    public function can(BackedEnum|array|string $abilities, UserSurface $surface): bool
    {
        return $this->connector->can($this->normalizeAbilities($abilities), $surface);
    }

    /**
     * @param  BackedEnum|list<BackedEnum|string>|string  $abilities
     * @return list<string>|string
     */
    private function normalizeAbilities(BackedEnum|array|string $abilities): array|string
    {
        if ($abilities instanceof BackedEnum) {
            return (string) $abilities->value;
        }

        if (is_array($abilities)) {
            return array_map(
                static fn (BackedEnum|string $ability): string => $ability instanceof BackedEnum
                    ? (string) $ability->value
                    : $ability,
                $abilities,
            );
        }

        return $abilities;
    }
}
