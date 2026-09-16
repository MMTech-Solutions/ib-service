<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions\Catalog;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostgreSqlSubscriptionConstraintTest extends TestCase
{
    use RefreshDatabase;

    private string $planId;

    private string $programId;

    private string $externalUserId;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL constraints require a pgsql connection.');
        }

        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create([
            'plan_id' => $plan->id,
            'code' => 'basic',
            'position' => 1,
            'entry_threshold' => 0,
        ]);

        $this->planId = (string) $plan->id;
        $this->programId = (string) $program->id;
        $this->externalUserId = (string) Str::uuid7();
    }

    public function test_it_rejects_invalid_status_and_origin_shapes(): void
    {
        $this->expectExceptionMessageMatches('/subscriptions_status_shape_check|check constraint/i');

        DB::table('subscriptions')->insert($this->subscriptionRow([
            'status' => 'pending',
            'activated_at' => now('UTC'),
        ]));
    }

    public function test_it_rejects_duplicate_open_subscriptions_for_the_same_user(): void
    {
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'status' => 'pending',
        ]));

        $this->expectExceptionMessageMatches('/subscriptions_open_user_unique|unique/i');

        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => (string) Str::uuid7(),
            'status' => 'active',
            'activated_at' => now('UTC'),
            'requires_approval' => false,
        ]));
    }

    public function test_it_rejects_replacing_the_same_subscription_twice(): void
    {
        $createdAt = now('UTC')->subHours(2);
        $originalId = (string) Str::uuid7();
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => $originalId,
            'status' => 'ended',
            'activated_at' => $createdAt->copy()->addHour(),
            'closed_at' => $createdAt->copy()->addHours(2),
            'requires_approval' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addHours(2),
        ]));

        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => (string) Str::uuid7(),
            'external_user_id' => (string) Str::uuid7(),
            'origin' => 'admin_plan_change',
            'requires_approval' => null,
            'status' => 'ended',
            'activated_at' => $createdAt->copy()->addHour(),
            'closed_at' => $createdAt->copy()->addHours(2),
            'replaces_subscription_id' => $originalId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addHours(2),
        ]));

        $this->expectExceptionMessageMatches('/subscriptions_replaces_unique|unique/i');

        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => (string) Str::uuid7(),
            'external_user_id' => (string) Str::uuid7(),
            'origin' => 'admin_plan_change',
            'requires_approval' => null,
            'status' => 'active',
            'activated_at' => now('UTC'),
            'replaces_subscription_id' => $originalId,
        ]));
    }

    public function test_it_rejects_reject_history_without_a_non_empty_reason(): void
    {
        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => $subscriptionId,
            'status' => 'rejected',
            'closed_at' => now('UTC'),
        ]));

        $this->expectExceptionMessageMatches('/subscription_changes_reject_reason_check|check constraint/i');

        DB::table('subscription_changes')->insert([
            'id' => (string) Str::uuid7(),
            'operation_id' => (string) Str::uuid7(),
            'subscription_id' => $subscriptionId,
            'action' => 'reject',
            'actor_kind' => 'iam',
            'actor_external_user_id' => (string) Str::uuid7(),
            'reason' => '   ',
            'previous_status' => 'pending',
            'next_status' => 'rejected',
            'previous_program_id' => null,
            'next_program_id' => null,
            'previous_is_fixed' => null,
            'next_is_fixed' => null,
            'occurred_at' => now('UTC'),
        ]);
    }

    public function test_it_rejects_two_open_placements_for_the_same_subscription(): void
    {
        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => $subscriptionId,
            'status' => 'active',
            'activated_at' => now('UTC'),
            'requires_approval' => false,
        ]));

        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid7(),
            'subscription_id' => $subscriptionId,
            'program_id' => $this->programId,
            'is_fixed' => false,
            'effective_from' => now('UTC'),
            'effective_until' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->expectExceptionMessageMatches('/subscription_placements_open_unique|unique/i');

        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid7(),
            'subscription_id' => $subscriptionId,
            'program_id' => $this->programId,
            'is_fixed' => false,
            'effective_from' => now('UTC')->addHour(),
            'effective_until' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    public function test_foreign_keys_restrict_program_deletion(): void
    {
        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => $subscriptionId,
            'status' => 'active',
            'activated_at' => now('UTC'),
            'requires_approval' => false,
        ]));
        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid7(),
            'subscription_id' => $subscriptionId,
            'program_id' => $this->programId,
            'is_fixed' => false,
            'effective_from' => now('UTC'),
            'effective_until' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->expectExceptionMessageMatches('/foreign key|restrict/i');
        DB::table('programs')->where('id', $this->programId)->delete();
    }

    public function test_foreign_keys_restrict_subscription_deletion_when_placements_exist(): void
    {
        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'id' => $subscriptionId,
            'status' => 'active',
            'activated_at' => now('UTC'),
            'requires_approval' => false,
        ]));
        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid7(),
            'subscription_id' => $subscriptionId,
            'program_id' => $this->programId,
            'is_fixed' => false,
            'effective_from' => now('UTC'),
            'effective_until' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->expectExceptionMessageMatches('/foreign key|restrict/i');
        DB::table('subscriptions')->where('id', $subscriptionId)->delete();
    }

    public function test_foreign_keys_restrict_plan_deletion_when_subscriptions_exist(): void
    {
        DB::table('subscriptions')->insert($this->subscriptionRow([
            'status' => 'pending',
        ]));

        $this->expectExceptionMessageMatches('/foreign key|restrict/i');
        DB::table('plans')->where('id', $this->planId)->delete();
    }

    /** @param  array<string, mixed>  $overrides */
    private function subscriptionRow(array $overrides = []): array
    {
        $now = now('UTC');

        return array_merge([
            'id' => (string) Str::uuid7(),
            'external_user_id' => $this->externalUserId,
            'plan_id' => $this->planId,
            'origin' => 'user_application',
            'requires_approval' => true,
            'status' => 'pending',
            'activated_at' => null,
            'closed_at' => null,
            'replaces_subscription_id' => null,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }
}
