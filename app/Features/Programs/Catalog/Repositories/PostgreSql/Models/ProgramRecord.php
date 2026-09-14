<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql\Models;

use Database\Factories\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProgramRecord extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'programs';

    protected $guarded = [];

    public $timestamps = false;

    protected static function newFactory(): ProgramRecordFactory
    {
        return ProgramRecordFactory::new();
    }

    /** @return HasMany<ProgramModuleSelectionRecord, $this> */
    public function selections(): HasMany
    {
        return $this->hasMany(ProgramModuleSelectionRecord::class, 'program_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'lock_version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
