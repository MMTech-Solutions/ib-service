<?php

declare(strict_types=1);

namespace Tests\Support;

trait VolumeRewardEventFixtures
{
    /** @return array<string, mixed> */
    private function activity(string $provider = 'broker'): array
    {
        return [
            'source_activity_id' => $provider.':position:aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'subject_external_user_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'metric_code' => 'closed_trading_volume', 'unit_code' => 'lot', 'quantity' => '1.50000000',
            'occurred_at' => '2026-10-06T10:00:00.123Z', 'server_group_id' => 'group', 'symbol_id' => 'symbol',
            'currency_code' => 'USD', 'currency_precision' => 2, 'broker_granted_commission' => '12.5000',
        ];
    }

    /** @return array<string, mixed> */
    private function body(string $provider = 'broker'): array
    {
        return ['event_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'schema_version' => 1, 'provider_code' => $provider, 'activity' => $this->activity($provider)];
    }
}
