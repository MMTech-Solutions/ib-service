<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RuleVersionRecord extends Model
{
    use HasUuids;

    protected $table = 'rule_versions';

    protected $guarded = [];

    public $timestamps = false;

    /** @return BelongsTo<RuleRecord, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(RuleRecord::class, 'rule_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'schema_version' => 'integer',
            'configuration' => 'array',
            'lock_version' => 'integer',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
