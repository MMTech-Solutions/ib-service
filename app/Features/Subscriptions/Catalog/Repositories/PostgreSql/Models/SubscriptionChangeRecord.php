<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionChangeRecord extends Model
{
    use HasUuids;

    protected $table = 'subscription_changes';

    protected $guarded = [];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_is_fixed' => 'boolean',
            'next_is_fixed' => 'boolean',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
