<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class SettlePendingRewardsUseCase
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RewardSettlementGatewayInterface $gateway,
    ) {}

    /** @return array{settled: int, failed: int, skipped: int} */
    public function execute(int $limit): array
    {
        $result = ['settled' => 0, 'failed' => 0, 'skipped' => 0];

        for ($processed = 0; $processed < $limit; $processed++) {
            $claim = $this->claimNext();
            if ($claim === null) {
                break;
            }

            try {
                $settlement = $this->gateway->settle(new RewardSettlementRequestData(
                    reward_id: (string) $claim->id,
                    beneficiary_user_id: (string) $claim->beneficiary_user_id,
                    amount_minor: (int) $claim->amount_minor,
                    currency_code: (string) $claim->currency_code,
                    currency_precision: (int) $claim->currency_precision,
                    idempotency_key: (string) $claim->settlement_idempotency_key,
                ));
                $this->markSettled((string) $claim->id, (string) $claim->settlement_lock_token, $settlement->provider, $settlement->reference_id);
                $result['settled']++;
            } catch (RewardSettlementException $exception) {
                $this->markFailed((string) $claim->id, (string) $claim->settlement_lock_token, $exception->error_code);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function claimNext(): ?object
    {
        $now = CarbonImmutable::now('UTC');
        $retryAt = $now->subSeconds((int) config('rewards.settlement.retry_delay_seconds', 300));
        $lockExpiresAt = $now->addSeconds((int) config('rewards.settlement.claim_lease_seconds', 60));

        return $this->connection->transaction(function () use ($now, $retryAt, $lockExpiresAt): ?object {
            $reward = $this->connection->table('rewards')
                ->join('cpa_contexts', 'cpa_contexts.reward_id', '=', 'rewards.id')
                ->join('rules', 'rules.id', '=', 'rewards.rule_id')
                ->where('rules.strategy_type', 'cpa_fixed_amount')
                ->where(function ($query) use ($retryAt): void {
                    $query->where('rewards.status', 'pending')
                        ->orWhere(function ($retryable) use ($retryAt): void {
                            $retryable->where('rewards.status', 'failed')
                                ->where(function ($lastAttempt) use ($retryAt): void {
                                    $lastAttempt->whereNull('rewards.last_settlement_attempt_at')
                                        ->orWhere('rewards.last_settlement_attempt_at', '<=', $retryAt);
                                });
                        });
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('rewards.settlement_lock_expires_at')
                        ->orWhere('rewards.settlement_lock_expires_at', '<=', $now);
                })
                ->orderBy('rewards.created_at')
                ->lockForUpdate()
                ->first(['rewards.*']);

            if ($reward === null) {
                return null;
            }

            $token = (string) Str::uuid7();
            $key = $reward->settlement_idempotency_key ?? 'ib-service:reward:'.$reward->id.':settlement';
            $this->connection->table('rewards')->where('id', $reward->id)->update([
                'settlement_idempotency_key' => $key,
                'settlement_attempt_count' => (int) $reward->settlement_attempt_count + 1,
                'last_settlement_attempt_at' => $now,
                'settlement_lock_token' => $token,
                'settlement_lock_expires_at' => $lockExpiresAt,
                'updated_at' => $now,
            ]);

            $reward->settlement_idempotency_key = $key;
            $reward->settlement_lock_token = $token;

            return $reward;
        });
    }

    private function markSettled(string $rewardId, string $token, string $provider, string $referenceId): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('rewards')->where('id', $rewardId)->where('settlement_lock_token', $token)->update([
            'status' => 'settled',
            'settlement_provider' => $provider,
            'settlement_reference_id' => $referenceId,
            'last_settlement_error_code' => null,
            'settled_at' => $now,
            'settlement_lock_token' => null,
            'settlement_lock_expires_at' => null,
            'updated_at' => $now,
        ]);
    }

    private function markFailed(string $rewardId, string $token, string $errorCode): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('rewards')->where('id', $rewardId)->where('settlement_lock_token', $token)->update([
            'status' => 'failed',
            'last_settlement_error_code' => $errorCode,
            'settlement_lock_token' => null,
            'settlement_lock_expires_at' => null,
            'updated_at' => $now,
        ]);
    }
}
