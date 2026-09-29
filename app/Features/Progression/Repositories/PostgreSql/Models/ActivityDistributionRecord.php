<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ActivityDistributionRecord extends Model
{
    use HasUuids;

    protected $table = 'progression_activity_distributions';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<DistributionBeneficiaryRecord, $this> */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(DistributionBeneficiaryRecord::class, 'distribution_id');
    }
}
