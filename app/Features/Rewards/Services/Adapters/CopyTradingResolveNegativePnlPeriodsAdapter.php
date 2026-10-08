<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Services\NegativePnlPeriodsResponseValidator;

final class CopyTradingResolveNegativePnlPeriodsAdapter implements ResolveNegativePnlPeriodsPort
{
    public function __construct(private readonly CopyTradingNegativePnlApiClient $client, private readonly NegativePnlPeriodsResponseValidator $validator) {}

    public function resolve(ResolveNegativePnlPeriodsQueryData $query): ResolveNegativePnlPeriodsResultData
    {
        return $this->validator->validate($query, $this->client->resolve([
            'subjects' => array_map(static fn ($subject): array => $subject->toArray(), $query->subjects),
            'occurred_from' => $query->occurred_from,
            'occurred_until' => $query->occurred_until,
        ]));
    }
}
