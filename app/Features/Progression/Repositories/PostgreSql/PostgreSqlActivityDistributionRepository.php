<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql;

use App\Features\Progression\Contracts\Repositories\ActivityDistributionRepositoryInterface;
use App\Features\Progression\Models\ActivityDistribution;
use App\Features\Progression\Models\DistributionBeneficiary;
use App\Features\Progression\Repositories\PostgreSql\Models\ActivityDistributionRecord;
use App\Features\Progression\Repositories\PostgreSql\Models\DistributionBeneficiaryRecord;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlActivityDistributionRepository implements ActivityDistributionRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findBySourceActivity(string $moduleId, string $sourceActivityId): ?ActivityDistribution
    {
        $record = ActivityDistributionRecord::query()->with('beneficiaries')->where('module_id', $moduleId)->where('source_activity_id', $sourceActivityId)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function record(ActivityDistribution $distribution): ActivityDistribution
    {
        return $this->transaction(function () use ($distribution): ActivityDistribution {
            $this->connection->statement('SAVEPOINT progression_record_distribution');

            try {
                ActivityDistributionRecord::query()->create(['id' => $distribution->id, 'module_id' => $distribution->moduleId, 'source_activity_id' => $distribution->sourceActivityId, 'source_external_user_id' => $distribution->sourceExternalUserId, 'resolved_at' => $distribution->resolvedAt, 'created_at' => $distribution->createdAt, 'updated_at' => $distribution->updatedAt]);
                foreach ($distribution->beneficiaries as $beneficiary) {
                    DistributionBeneficiaryRecord::query()->create(['distribution_id' => $distribution->id, 'beneficiary_external_user_id' => $beneficiary->beneficiaryExternalUserId, 'distribution_level' => $beneficiary->distributionLevel, 'created_at' => $distribution->createdAt, 'updated_at' => $distribution->updatedAt]);
                }

                $this->connection->statement('RELEASE SAVEPOINT progression_record_distribution');
            } catch (UniqueConstraintViolationException $exception) {
                $this->connection->statement('ROLLBACK TO SAVEPOINT progression_record_distribution');
                $canonical = $this->findBySourceActivity($distribution->moduleId, $distribution->sourceActivityId);
                if ($canonical !== null) {
                    return $canonical;
                }
                throw $exception;
            }

            return $this->findBySourceActivity($distribution->moduleId, $distribution->sourceActivityId) ?? $distribution;
        });
    }

    private function hydrate(ActivityDistributionRecord $record): ActivityDistribution
    {
        $beneficiaries = $record->beneficiaries->sortBy('distribution_level')->map(fn (DistributionBeneficiaryRecord $item): DistributionBeneficiary => new DistributionBeneficiary((string) $item->beneficiary_external_user_id, (int) $item->distribution_level))->values()->all();

        return ActivityDistribution::reconstitute((string) $record->id, (string) $record->module_id, (string) $record->source_activity_id, (string) $record->source_external_user_id, CarbonImmutable::instance($record->resolved_at), $beneficiaries, CarbonImmutable::instance($record->created_at), CarbonImmutable::instance($record->updated_at));
    }
}
