<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class DistributionBeneficiaryRecord extends Model
{
    use HasUuids;

    protected $table = 'progression_activity_distribution_beneficiaries';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['distribution_level' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
