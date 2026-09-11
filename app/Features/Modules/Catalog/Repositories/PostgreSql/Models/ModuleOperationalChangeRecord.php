<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleOperationalChangeRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ModuleOperationalChangeRecord extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'module_operational_changes';

    protected $fillable = [
        'id',
        'module_id',
        'action',
        'actor_iam_id',
        'reason',
        'previous_is_active',
        'previous_processing_status',
        'next_is_active',
        'next_processing_status',
        'occurred_at',
    ];

    protected static function newFactory(): ModuleOperationalChangeRecordFactory
    {
        return ModuleOperationalChangeRecordFactory::new();
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
