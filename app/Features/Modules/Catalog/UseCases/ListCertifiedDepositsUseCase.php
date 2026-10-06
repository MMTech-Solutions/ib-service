<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Contracts\Data\V1\CertifiedDepositEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCertifiedDepositsQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Ports\Input\ListCertifiedDepositsPort;
use App\Features\Modules\Sources\Broker\Services\FinanceCertifiedDepositsApiClient;
use Carbon\CarbonImmutable;

final class ListCertifiedDepositsUseCase implements ListCertifiedDepositsPort
{
    public function __construct(private readonly FinanceCertifiedDepositsApiClient $finance) {}

    public function execute(ListCertifiedDepositsQueryData $query): array
    {
        $from = CarbonImmutable::parse($query->occurred_from)->utc();
        $until = CarbonImmutable::parse($query->occurred_until)->utc();
        if (! $from->lt($until)) {
            throw InvalidProgressionActivityQueryException::withMessage('A valid deposit interval is required.');
        }
        $facts = [];
        foreach ($this->finance->list($query->subject_external_user_id, $from->toIso8601String(), $until->toIso8601String(), $query->currency_code) as $deposit) {
            if (($deposit['minor_units'] ?? null) !== $query->currency_precision || ! is_int($deposit['amount_minor'] ?? null) || $deposit['amount_minor'] <= 0 || ! is_int($deposit['id'] ?? null) || ($deposit['currency_code'] ?? null) !== $query->currency_code || ! is_string($deposit['credited_at'] ?? null)) {
                throw InvalidProgressionActivityQueryException::withMessage('Finance returned an invalid certified deposit.');
            }
            $facts[] = new CertifiedDepositEvidenceData('finance:ledger:'.$deposit['id'], $query->subject_external_user_id, $deposit['amount_minor'], $query->currency_code, $deposit['credited_at']);
        }

        return $facts;
    }
}
