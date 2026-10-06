<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rewards\DTOs\CpaContributionData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CpaFixtures;
use Tests\TestCase;

final class CpaRewardCalculationPersistenceTest extends TestCase
{
    use CpaFixtures, RefreshDatabase;

    public function test_configuration_changes_do_not_revalue_points_or_duplicate_reward(): void
    {
        $f = $this->cpaFixture(twoModules: false);
        $original = $f['context']->requirements_snapshot;
        $sources = $f['repository']->listCpaSources($f['context']->id);
        $at = CarbonImmutable::now('UTC');
        $f['repository']->persistCpaSource($f['context'], $sources[1], [new CpaContributionData('trading', 'p', $f['referred'], 'volume', '5', 'lot', '20', '100', $at->subMinute()->toISOString(), 'symbol')], $at);
        $f['repository']->persistCpaSource($f['context'], $sources[0], [new CpaContributionData('finance', 'd', $f['referred'], 'deposit', '500', 'USD', '0.2', '100', $at->subMinute()->toISOString(), amount_minor: 50000, currency_code: 'USD')], $at);
        DB::table('program_cpa_rule_assignments')->where('id', $f['assignment'])->update(['ends_at' => $at]);
        self::assertSame('qualified', $f['repository']->completeCpaVerification($f['context'], $at));
        self::assertSame('already_qualified', $f['repository']->completeCpaVerification($f['context'], $at->addHour()));
        self::assertSame($original, DB::table('cpa_contexts')->value('requirements_snapshot'));
        self::assertSame(1, DB::table('rewards')->count());
        self::assertSame('100.0000000000000000', DB::table('cpa_verification_progress')->value('observed_volume_points'));
        self::assertSame('20.00000000', DB::table('cpa_contributions')->where('kind', 'volume')->value('points_per_unit'));
        self::assertFalse($f['repository']->captureCpaContext($f['data'], (object) [], (object) [], [], [])['created']);
    }
}
