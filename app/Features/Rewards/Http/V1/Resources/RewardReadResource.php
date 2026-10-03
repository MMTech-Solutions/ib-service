<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Resources;

use App\Features\Rewards\DTOs\RewardReadData;

final readonly class RewardReadResource
{
    public function __construct(private RewardReadData $reward) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = $this->reward->toArray();
        unset($data['audit']);
        if ($this->reward->audit !== null) {
            $data['audit'] = $this->reward->audit;
        }

        return $data;
    }
}
