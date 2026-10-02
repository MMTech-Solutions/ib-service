<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Resources;

use App\Features\Rewards\DTOs\CpaVerificationProgressData;

final readonly class CpaVerificationProgressResource
{
    public function __construct(
        private CpaVerificationProgressData $progress,
        private bool $isAdministrative,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->progress->id,
            'referred_user_id' => $this->progress->referred_user_id,
            'status' => $this->progress->status,
            'observed_volume' => $this->progress->observed_volume,
            'required_volume' => $this->progress->required_volume,
            'volume_unit_code' => $this->progress->volume_unit_code,
            'observed_deposit_minor' => $this->progress->observed_deposit_minor,
            'required_deposit_minor' => $this->progress->required_deposit_minor,
            'currency_code' => $this->progress->currency_code,
            'currency_precision' => $this->progress->currency_precision,
            'volume_satisfied' => $this->progress->volume_satisfied,
            'deposit_satisfied' => $this->progress->deposit_satisfied,
            'observed_from' => $this->progress->observed_from,
            'observed_until' => $this->progress->observed_until,
            'last_evaluated_at' => $this->progress->last_evaluated_at,
        ];

        if (! $this->isAdministrative) {
            return $data;
        }

        return [...$data, ...[
            'ib_user_id' => $this->progress->ib_user_id,
            'plan_id' => $this->progress->plan_id,
            'program_id' => $this->progress->program_id,
            'module_id' => $this->progress->module_id,
            'rule_assignment_id' => $this->progress->rule_assignment_id,
            'rule_id' => $this->progress->rule_id,
            'rule_version_id' => $this->progress->rule_version_id,
            'reward_id' => $this->progress->reward_id,
            'reward_financial_status' => $this->progress->reward_financial_status,
            'reward_reconciliation_hold_code' => $this->progress->reward_reconciliation_hold_code,
            'last_error_code' => $this->progress->last_error_code,
        ]];
    }
}
