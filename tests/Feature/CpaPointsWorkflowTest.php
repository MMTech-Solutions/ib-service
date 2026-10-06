<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Contracts\Data\V1\CertifiedDepositEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ListCertifiedDepositsPort;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CpaContributionData;
use App\Features\Rewards\Exceptions\CpaEvidenceContractException;
use App\Features\Rewards\UseCases\CaptureCpaContextUseCase;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\CpaFixtures;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class CpaPointsWorkflowTest extends TestCase
{
    use CpaFixtures, InteractsWithAdminGateway, RefreshDatabase;

    public function test_partial_runs_preserve_each_contribution_and_qualify_using_unavailable_sources(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $f = $this->cpaFixture();
        $mode = 1;
        $cutoff = now('UTC')->toISOString();
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->andReturnUsing(function ($ids) use ($f, &$mode): array {
            return [new ModuleSummaryData($ids[0], $ids[0] === $f['modules'][0]->id ? 'broker' : 'cpa-test', 'Module', ! ($mode === 2 && $ids[0] === $f['modules'][0]->id), 'running')];
        });
        app()->instance(ResolveModulesPort::class, $modules);
        $provider = Mockery::mock(ListCpaEvidencePort::class);
        $provider->shouldReceive('list')->andReturnUsing(function ($query) use ($f, &$mode): CpaEvidenceData {
            if ($query->module_id === $f['modules'][1]->id && $mode === 1) {
                throw new \RuntimeException('unavailable');
            }
            $fact = new CpaVolumeEvidenceData('same-position', $f['referred'], 'closed_trading_volume', 'lot', $query->module_id === $f['modules'][0]->id ? '2' : '1', now('UTC')->subMinute()->toISOString(), 'symbol', 'trading');

            return new CpaEvidenceData([$fact, $fact], []);
        });
        app()->instance(ListCpaEvidencePort::class, $provider);
        $finance = Mockery::mock(ListCertifiedDepositsPort::class);
        $finance->shouldReceive('execute')->twice()->andReturnUsing(function ($query) use ($f, &$mode): array {
            return [new CertifiedDepositEvidenceData('deposit-'.$mode, $f['referred'], $mode === 1 ? 30000 : 20000, 'USD', now('UTC')->subMinute()->toISOString())];
        });
        app()->instance(ListCertifiedDepositsPort::class, $finance);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);
        $useCase = app(VerifyCpaContextsUseCase::class);
        self::assertSame(1, $useCase->execute(10)['errored']);
        self::assertSame(2, DB::table('cpa_contributions')->count());
        $first = DB::table('cpa_sources')->where('module_id', $f['modules'][0]->id)->first();
        self::assertNotNull($first->observed_until);
        self::assertSame('40.0000000000000000', DB::table('cpa_verification_progress')->value('observed_volume_points'));
        $mode = 2;
        $this->travel(1)->hours();
        app(DomainClock::class)->end();
        self::assertSame(1, $useCase->execute(10)['qualified']);
        self::assertSame(0, $useCase->execute(10)['qualified']);
        self::assertSame(4, DB::table('cpa_contributions')->count());
        self::assertSame($first->observed_until, DB::table('cpa_sources')->where('id', $first->id)->value('observed_until'));
        $reward = DB::table('rewards')->sole();
        self::assertNull($reward->module_id);
        self::assertNull($reward->rule_assignment_id);
        self::assertSame('EUR', $reward->currency_code);
        self::assertSame(2500, $reward->amount_minor);
        self::assertSame(4, DB::table('reward_evidence')->count());
        self::assertSame('100.0000000000000000', DB::table('cpa_verification_progress')->value('observed_volume_points'));
        self::assertSame('100.0000000000000000', DB::table('cpa_verification_progress')->value('observed_deposit_points'));
        self::assertSame(50000, DB::table('cpa_verification_progress')->value('observed_deposit_minor'));
        self::assertSame(2, DB::table('cpa_contributions')->where('source_activity_id', 'same-position')->count());
    }

    public function test_persistence_rejects_mutation_and_late_facts_and_keeps_cutoff(): void
    {
        $f = $this->cpaFixture(twoModules: false);
        $source = $f['repository']->listCpaSources($f['context']->id)[1];
        $at = CarbonImmutable::now('UTC');
        $fact = new CpaContributionData('trading', 'p', $f['referred'], 'volume', '2', 'lot', '20', '40', $at->subMinute()->toISOString(), 'symbol');
        $f['repository']->persistCpaSource($f['context'], $source, [$fact, $fact], $at);
        self::assertSame(1, DB::table('cpa_contributions')->count());
        foreach ([
            new CpaContributionData('trading', 'p', $f['referred'], 'volume', '3', 'lot', '20', '60', $fact->occurred_at, 'symbol'),
            new CpaContributionData('trading', 'late', $f['referred'], 'volume', '2', 'lot', '20', '40', $fact->occurred_at, 'symbol'),
        ] as $invalid) {
            try {
                $f['repository']->persistCpaSource($f['context'], $source, [$invalid], $at->addHour());
                self::fail('Expected immutable source rejection.');
            } catch (CpaEvidenceContractException) {
                self::assertSame(1, DB::table('cpa_contributions')->count());
            }
        }
    }

    public function test_capture_uses_ib_subscription_and_preserves_context_after_configuration_withdrawal(): void
    {
        $f = $this->cpaFixture(twoModules: false);
        $at = now('UTC')->subDay();
        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert(['id' => $subscriptionId, 'external_user_id' => $f['ib'], 'plan_id' => $f['plan']->id, 'origin' => 'user_application', 'requires_approval' => false, 'status' => 'active', 'activated_at' => $at, 'lock_version' => 1, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('subscription_placements')->insert(['id' => (string) Str::uuid7(), 'subscription_id' => $subscriptionId, 'program_id' => $f['program']->id, 'is_fixed' => false, 'effective_from' => $at, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('modules')->where('id', $f['modules'][0]->id)->update(['is_active' => false, 'processing_status' => 'paused']);
        $data = new CaptureCpaContextData((string) Str::uuid7(), $f['ib'], now('UTC')->toISOString());
        $result = app(CaptureCpaContextUseCase::class)->execute($data);
        self::assertTrue($result->created);
        self::assertSame(2, DB::table('cpa_sources')->where('cpa_context_id', $result->cpa_context_id)->count());
        DB::table('program_cpa_rule_assignments')->where('id', $f['assignment'])->update(['ends_at' => now('UTC')]);
        $repeat = app(CaptureCpaContextUseCase::class)->execute($data);
        self::assertFalse($repeat->created);
        self::assertSame($result->cpa_context_id, $repeat->cpa_context_id);
    }

    public function test_administrative_configuration_is_idempotent_historical_and_authorized(): void
    {
        $this->seedAuthorizedAdmin();
        $f = $this->cpaFixture(twoModules: false);
        $url = '/api/ib/v1/admin/programs/'.$f['program']->id.'/cpa-configuration';
        $this->gatewayJson('GET', $url)->assertOk()->assertJsonPath('data.id', $f['assignment']);
        $this->gatewayJson('PUT', $url, ['rule_version_id' => $f['version']->id])->assertOk()->assertJsonPath('data.id', $f['assignment']);
        $this->gatewayJson('DELETE', $url)->assertNoContent();
        $this->gatewayJson('DELETE', $url)->assertNoContent();
        $this->gatewayJson('GET', $url)->assertOk()->assertJsonPath('data.configured', false);
        $this->gatewayJson('PUT', $url, ['rule_version_id' => $f['version']->id])->assertOk();
        self::assertSame(2, DB::table('program_cpa_rule_assignments')->count());
        self::assertSame(1, DB::table('program_cpa_rule_assignments')->whereNull('ends_at')->count());
        $this->gatewayJson('PUT', $url, ['rule_version_id' => (string) Str::uuid7()])->assertUnprocessable();
        $this->assertGatewayAuthGuards('GET', $url);
    }
}
