<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class RuleAssignmentRecord extends Model
{
    use HasUuids;

    protected $table = 'rule_assignments';

    protected $guarded = [];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
