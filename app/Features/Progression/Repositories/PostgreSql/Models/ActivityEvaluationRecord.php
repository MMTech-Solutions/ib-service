<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ActivityEvaluationRecord extends Model
{
    use HasUuids;

    protected $table = 'progression_activity_evaluations';

    protected $guarded = [];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'string',
            'occurred_at' => 'immutable_datetime',
            'distribution_resolved_at' => 'immutable_datetime',
            'window_starts_at' => 'immutable_datetime',
            'window_ends_at' => 'immutable_datetime',
            'evaluated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasOne<ContributionRecord, $this> */
    public function contribution(): HasOne
    {
        return $this->hasOne(ContributionRecord::class, 'evaluation_id');
    }
}
