<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramModuleSelectionRecord extends Model
{
    use HasUuids;

    protected $table = 'program_module_selections';

    protected $guarded = [];

    public $timestamps = false;

    /** @return BelongsTo<ProgramRecord, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramRecord::class, 'program_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
