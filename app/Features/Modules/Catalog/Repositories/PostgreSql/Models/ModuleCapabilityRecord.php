<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleCapabilityRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ModuleCapabilityRecord extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'module_capabilities';

    protected $guarded = [];

    protected static function newFactory(): ModuleCapabilityRecordFactory
    {
        return ModuleCapabilityRecordFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
