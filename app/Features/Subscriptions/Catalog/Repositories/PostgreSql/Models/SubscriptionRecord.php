<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Repositories\PostgreSql\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionRecord extends Model
{
    use HasUuids;

    protected $table = 'subscriptions';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<SubscriptionPlacementRecord, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(SubscriptionPlacementRecord::class, 'subscription_id');
    }

    /** @return HasMany<SubscriptionChangeRecord, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionChangeRecord::class, 'subscription_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'lock_version' => 'integer',
            'activated_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
