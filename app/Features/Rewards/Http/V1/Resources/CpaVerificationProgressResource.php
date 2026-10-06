<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Resources;

use App\Features\Rewards\DTOs\CpaSourceProgressData;
use App\Features\Rewards\DTOs\CpaVerificationProgressData;

final readonly class CpaVerificationProgressResource
{
    public function __construct(private CpaVerificationProgressData $progress, private bool $isAdministrative) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = $this->progress->toArray();
        $data['sources'] = array_map(function (CpaSourceProgressData $source): array {
            $item = $source->toArray();
            $item['unit_code'] = $source->kind === 'volume' ? 'lot' : $this->progress->deposit_currency_code;
            if (! $this->isAdministrative) {
                unset($item['last_error_code']);
            }

            return $item;
        }, $this->progress->sources);
        if (! $this->isAdministrative) {
            foreach (['ib_user_id', 'plan_id', 'program_id', 'cpa_assignment_id', 'rule_id', 'rule_version_id', 'reward_id', 'last_error_code', 'reward_financial_status', 'reward_reconciliation_hold_code'] as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
