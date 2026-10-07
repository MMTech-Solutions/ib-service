<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Support;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class VolumeRewardActivityValidator
{
    /** @param array<string, mixed> $item */
    public function normalize(string $provider, string $moduleId, array $item): VolumeRewardActivityData
    {
        foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'server_group_id', 'symbol_id', 'currency_code', 'broker_granted_commission'] as $field) {
            if (! is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                throw InvalidVolumeRewardActivityException::create();
            }
        }
        if (! str_starts_with($item['source_activity_id'], $provider.':position:') || strlen($item['source_activity_id']) > 255 || substr($item['source_activity_id'], strlen($provider.':position:')) === '' || ! Str::isUuid($item['subject_external_user_id']) || $item['metric_code'] !== 'closed_trading_volume' || $item['unit_code'] !== 'lot' || ! preg_match('/^[A-Z]{3}$/D', $item['currency_code']) || ! is_int($item['currency_precision'] ?? null) || $item['currency_precision'] < 0 || $item['currency_precision'] > 10) {
            throw InvalidVolumeRewardActivityException::create();
        }
        foreach (['quantity', 'broker_granted_commission'] as $field) {
            if (! preg_match('/^(0|[1-9][0-9]{0,19})(\\.[0-9]{1,10})?$/D', $item[$field])) {
                throw InvalidVolumeRewardActivityException::create();
            }
        }
        foreach (['symbol_id', 'server_group_id'] as $field) {
            if (strlen($item[$field]) > 120 || str_contains($item[$field], ':') || $item[$field] !== trim($item[$field])) {
                throw InvalidVolumeRewardActivityException::create();
            }
        }
        if (! preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?(?:Z|\\+00:00)$/D', $item['occurred_at'])) {
            throw InvalidVolumeRewardActivityException::create();
        }
        try {
            $occurred = CarbonImmutable::parse($item['occurred_at'])->utc();
            if ($occurred->format('Y-m-d\\TH:i:s') !== substr($item['occurred_at'], 0, 19)) {
                throw InvalidVolumeRewardActivityException::create();
            }
        } catch (\Throwable) {
            throw InvalidVolumeRewardActivityException::create();
        }

        return new VolumeRewardActivityData(
            $moduleId, $item['source_activity_id'], strtolower($item['subject_external_user_id']), $item['unit_code'],
            $this->decimal($item['quantity']), $occurred->toISOString(),
            $provider.':server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
            $item['currency_code'], $item['currency_precision'], $this->decimal($item['broker_granted_commission']),
        );
    }

    private function decimal(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
