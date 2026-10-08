<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Contracts\Data\V1\CertifiedDepositEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Ports\Input\ListCertifiedDepositsPort;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ListProgressionActivitiesPort;
use App\Features\Modules\Sources\CopyTrading\Services\Adapters\CopyTradingClosedTradingVolumeActivityAdapter;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlSubjectData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\VolumeRewardEventFixtures;
use Tests\TestCase;

final class CopyTradingIntegrationTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\CpaFixtures, VolumeRewardEventFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('modules.sources.copy_trading.base_url', 'http://copy.test');
        config()->set('modules.sources.copy_trading.internal_token', 'copy-token');
        $this->artisan('modules:sync')->assertSuccessful();
        Http::preventStrayRequests();
    }

    public function test_cpa_consumes_all_pages_and_preserves_provider_identity(): void
    {
        $module = DB::table('modules')->where('code', 'copy_trading')->value('id');
        $second = $this->activity('copy_trading');
        $second['source_activity_id'] = 'copy_trading:position:dddddddd-dddd-4ddd-8ddd-dddddddddddd';
        Http::fake(['copy.test/*' => Http::sequence()
            ->push(['success' => true, 'data' => [$this->activity('copy_trading')], 'meta' => ['next_cursor' => 'page-two']])
            ->push(['success' => true, 'data' => [$second], 'meta' => ['next_cursor' => null]])]);
        $result = app(ListCpaEvidencePort::class)->list(new ListCpaEvidenceQueryData(
            $module, $second['subject_external_user_id'], '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z',
            [['symbol_reference' => 'copy_trading:server_group:group:symbol:symbol']],
        ));
        self::assertCount(2, $result->volume_facts);
        self::assertSame('copy_trading_service', $result->volume_facts[0]->provider);
        self::assertSame([], $result->deposit_facts);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['cursor'] === 'page-two' && $request->hasHeader('X-Internal-Token', 'copy-token'));
    }

    public function test_progression_selects_copy_provider_and_translates_resume_cursor(): void
    {
        $module = DB::table('modules')->where('code', 'copy_trading')->value('id');
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [$this->activity('copy_trading')], 'meta' => ['next_cursor' => null]])]);
        $query = new ListProgressionActivitiesQueryData($module, '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z');
        $result = app(ListProgressionActivitiesPort::class)->list($query);
        self::assertCount(1, $result->activities);
        self::assertSame($module, $result->activities[0]->module_id);
        self::assertSame('copy_trading:server_group:group:symbol:symbol', $result->activities[0]->instrument_reference);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/copy-trading/v1/internal/progression-activities'));
        app(CopyTradingClosedTradingVolumeActivityAdapter::class)->fetchPage(
            $module, CarbonImmutable::parse('2026-10-06T00:00:00Z'), CarbonImmutable::parse('2026-10-07T00:00:00Z'),
            '2026-10-06T09:00:00.123Z', 'copy_trading:position:resume-id', 10,
        );
        Http::assertSent(fn ($request) => isset($request['cursor']) && json_decode(base64_decode($request['cursor']), true)['id'] === 'resume-id');
    }

    public function test_missing_page_completion_is_rejected_for_cpa(): void
    {
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [], 'meta' => []])]);
        $module = DB::table('modules')->where('code', 'copy_trading')->value('id');
        $this->expectException(InvalidProgressionActivityQueryException::class);
        app(ListCpaEvidencePort::class)->list(new ListCpaEvidenceQueryData($module, 'user', '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z', []));
    }

    public function test_copy_cpa_http_volume_and_common_finance_deposit_create_one_pending_reward(): void
    {
        $f = $this->cpaFixture(twoModules: false, provider: 'copy_trading');
        $activity = $this->activity('copy_trading');
        $activity['subject_external_user_id'] = $f['referred'];
        $activity['quantity'] = '5';
        $activity['occurred_at'] = now('UTC')->subHour()->toISOString();
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [$activity], 'meta' => ['next_cursor' => null]])]);
        $finance = $this->createMock(ListCertifiedDepositsPort::class);
        $finance->expects(self::once())->method('execute')->willReturn([
            new CertifiedDepositEvidenceData('finance-deposit', $f['referred'], 50000, 'USD', now('UTC')->subHour()->toISOString()),
        ]);
        app()->instance(ListCertifiedDepositsPort::class, $finance);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);
        $verify = app(VerifyCpaContextsUseCase::class);
        self::assertSame(1, $verify->execute(10)['qualified'], json_encode(DB::table('cpa_sources')->get()->toArray()));
        self::assertSame(0, $verify->execute(10)['qualified']);
        self::assertSame(1, DB::table('rewards')->where('status', 'pending')->count());
        self::assertSame(2, DB::table('cpa_contributions')->count());
        self::assertSame('copy_trading_service', DB::table('cpa_contributions')->where('kind', 'volume')->value('provider'));
        Http::assertSentCount(1);
    }

    public function test_pnl_routes_by_module_and_rejects_incomplete_subjects(): void
    {
        $module = DB::table('modules')->where('code', 'copy_trading')->value('id');
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/Rewards/broker-negative-pnl-periods-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        Http::fake(['copy.test/*' => Http::response($fixture)]);
        $query = new ResolveNegativePnlPeriodsQueryData([new NegativePnlSubjectData('11111111-1111-4111-8111-111111111111')], '2026-10-02T10:00:00Z', '2026-10-01T10:00:00Z', $module);
        $result = app(ResolveNegativePnlPeriodsPort::class)->resolve($query);
        self::assertSame('-40.0000000000', $result->periods[1]->npnl);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('X-Internal-Token', 'copy-token') && str_contains($request->url(), '/copy-trading/v1/internal/accounts/negative-pnl-periods/resolve'));
        $fixture['meta']['completed_subjects'] = [];
        Http::swap(new Factory);
        Http::fake(['copy.test/*' => Http::response($fixture)]);
        $this->expectException(InvalidNegativePnlPeriodsResponseException::class);
        app(ResolveNegativePnlPeriodsPort::class)->resolve($query);
    }

    #[DataProvider('invalidActivities')]
    public function test_invalid_copy_activity_does_not_confirm_cpa_cut(string $field, string $value): void
    {
        $activity = $this->activity('copy_trading');
        $activity[$field] = $value;
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [$activity], 'meta' => ['next_cursor' => null]])]);
        $module = DB::table('modules')->where('code', 'copy_trading')->value('id');
        $this->expectException(InvalidProgressionActivityQueryException::class);
        app(ListCpaEvidencePort::class)->list(new ListCpaEvidenceQueryData($module, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z', [['symbol_reference' => 'copy_trading:server_group:group:symbol:symbol']]));
    }

    public static function invalidActivities(): array
    {
        return [
            ['source_activity_id', 'broker:position:foreign'],
            ['subject_external_user_id', 'foreign-user'],
            ['occurred_at', '2026-10-07T00:00:00Z'],
            ['quantity', '1.123456789'],
            ['unit_code', 'usd'],
            ['metric_code', 'confirmed_deposit'],
        ];
    }
}
