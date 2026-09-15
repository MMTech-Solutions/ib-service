<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RuleRecord extends Model
{
    use HasUuids;

    protected $table = 'rules';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<RuleVersionRecord, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RuleVersionRecord::class, 'rule_id')->orderBy('version_number');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
