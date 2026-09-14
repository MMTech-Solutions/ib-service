<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanOperationalChangeRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class PlanOperationalChangeRecord extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'plan_operational_changes';

    protected $guarded = [];

    protected static function newFactory(): PlanOperationalChangeRecordFactory
    {
        return PlanOperationalChangeRecordFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_is_active' => 'boolean',
            'next_is_active' => 'boolean',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
