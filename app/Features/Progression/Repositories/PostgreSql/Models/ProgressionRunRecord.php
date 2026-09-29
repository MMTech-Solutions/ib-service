<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ProgressionRunRecord extends Model
{
    use HasUuids;

    protected $table = 'progression_runs';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['window_starts_at' => 'immutable_datetime', 'window_ends_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
