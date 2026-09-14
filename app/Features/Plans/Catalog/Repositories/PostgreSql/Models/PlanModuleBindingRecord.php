<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanModuleBindingRecord extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'plan_module_bindings';

    protected $guarded = [];

    protected static function newFactory(): PlanModuleBindingRecordFactory
    {
        return PlanModuleBindingRecordFactory::new();
    }

    /** @return BelongsTo<PlanRecord, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanRecord::class, 'plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
