<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use App\Support\Messaging\Contracts\MessagePublisherInterface;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\CpaFixtures;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class CpaContextExpirationTest extends TestCase
{
    use CpaFixtures;
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    public function test_verify_expires_overdue_context_without_creating_reward_even_when_points_are_met(): void
    {
        Event::fake([CpaContextExpired::class]);
        $publisher = Mockery::mock(MessagePublisherInterface::class);
        $publisher->shouldReceive('publish')->never();
        app()->instance(MessagePublisherInterface::class, $publisher);

        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        $f = $this->cpaFixture(twoModules: false);
        $configuration = $f['configuration'];
        $configuration['expiration_days'] = 1;
        DB::table('cpa_contexts')->where('id', $f['context']->id)->update([
            'captured_at' => '2026-10-04T08:00:00Z',
            'requirements_snapshot' => json_encode($configuration, JSON_THROW_ON_ERROR),
        ]);
        $source = collect($f['repository']->listCpaSources($f['context']->id))->firstWhere('kind', 'volume');
        DB::table('cpa_contributions')->insert([
            'id' => (string) Str::uuid7(),
            'cpa_context_id' => $f['context']->id,
            'cpa_source_id' => $source->id,
            'provider' => 'trading',
            'source_activity_id' => 'already-enough',
            'subject_external_user_id' => $f['referred'],
            'kind' => 'volume',
            'quantity' => '10',
            'unit_code' => 'lot',
            'points_per_unit' => '20',
            'points' => '200',
            'occurred_at' => '2026-10-04T09:00:00Z',
            'instrument_reference' => 'symbol',
            'verified_until' => '2026-10-04T10:00:00Z',
            'created_at' => '2026-10-04T10:00:00Z',
        ]);
        $deposit = collect($f['repository']->listCpaSources($f['context']->id))->firstWhere('kind', 'deposit');
        DB::table('cpa_contributions')->insert([
            'id' => (string) Str::uuid7(),
            'cpa_context_id' => $f['context']->id,
            'cpa_source_id' => $deposit->id,
            'provider' => 'finance',
            'source_activity_id' => 'deposit-1',
            'subject_external_user_id' => $f['referred'],
            'kind' => 'deposit',
            'quantity' => '500',
            'unit_code' => 'USD',
            'points_per_unit' => '0.2',
            'points' => '100',
            'amount_minor' => 50000,
            'currency_code' => 'USD',
            'occurred_at' => '2026-10-04T09:00:00Z',
            'instrument_reference' => null,
            'verified_until' => '2026-10-04T10:00:00Z',
            'created_at' => '2026-10-04T10:00:00Z',
        ]);

        $context = DB::table('cpa_contexts')->where('id', $f['context']->id)->first();
        app()->forgetInstance(VerifyCpaContextsUseCase::class);
        $result = app(VerifyCpaContextsUseCase::class)->execute(10);

        self::assertSame(1, $result['expired']);
        self::assertSame(0, $result['qualified']);
        self::assertSame(0, DB::table('rewards')->count());
        self::assertSame('expired', DB::table('cpa_verification_progress')->where('cpa_context_id', $context->id)->value('status'));
        self::assertSame('waiting_period_exceeded', DB::table('cpa_verification_progress')->where('cpa_context_id', $context->id)->value('expiration_reason'));
        self::assertSame(0, count($f['repository']->listCpaContextsWithoutReward(10)));
        Event::assertDispatched(CpaContextExpired::class);
    }

    public function test_progress_api_exposes_expired_status_and_reason(): void
    {
        $this->seedAuthorizedAdmin();
        $this->seedAuthorizedCustomer();
        $f = $this->cpaFixture(LocalRbacSnapshotSeeder::CUSTOMER_SUB, false);
        DB::table('cpa_verification_progress')->where('cpa_context_id', $f['context']->id)->update([
            'status' => 'expired',
            'expiration_reason' => 'waiting_period_exceeded',
        ]);

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/cpa-progress?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $f['context']->id)
            ->assertJsonPath('data.0.status', 'expired')
            ->assertJsonPath('data.0.expiration_reason', 'waiting_period_exceeded');

        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/cpa-progress?status=expired')
            ->assertOk()
            ->assertJsonPath('data.0.expiration_reason', 'waiting_period_exceeded');
    }
}
