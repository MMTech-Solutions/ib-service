<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlanRecord extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'plans';

    protected $guarded = [];

    protected static function newFactory(): PlanRecordFactory
    {
        return PlanRecordFactory::new();
    }

    /** @return HasMany<PlanModuleBindingRecord, $this> */
    public function bindings(): HasMany
    {
        return $this->hasMany(PlanModuleBindingRecord::class, 'plan_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('deleted_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_approval' => 'boolean',
            'lock_version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
