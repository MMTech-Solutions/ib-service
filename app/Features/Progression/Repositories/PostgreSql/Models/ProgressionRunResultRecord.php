<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ProgressionRunResultRecord extends Model
{
    use HasUuids;

    protected $table = 'progression_run_results';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['total_points' => 'string', 'attempt_count' => 'integer', 'completed_at' => 'immutable_datetime'];
    }
}
