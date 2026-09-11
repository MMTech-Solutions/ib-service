<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ModuleRecord extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'modules';

    protected $guarded = [];

    protected static function newFactory(): ModuleRecordFactory
    {
        return ModuleRecordFactory::new();
    }

    /** @return HasMany<ModuleCapabilityRecord, $this> */
    public function capabilities(): HasMany
    {
        return $this->hasMany(ModuleCapabilityRecord::class, 'module_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lock_version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
