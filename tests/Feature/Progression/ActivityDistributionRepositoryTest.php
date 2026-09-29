<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Progression\Models\ActivityDistribution;
use App\Features\Progression\Models\DistributionBeneficiary;
use App\Features\Progression\Repositories\InMemory\InMemoryActivityDistributionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ActivityDistributionRepositoryTest extends TestCase
{
    public function test_it_returns_the_canonical_snapshot_on_retry(): void
    {
        $repository = new InMemoryActivityDistributionRepository;
        $moduleId = (string) Str::uuid7();
        $first = ActivityDistribution::resolve((string) Str::uuid7(), $moduleId, 'source', (string) Str::uuid7(), CarbonImmutable::now('UTC'), [new DistributionBeneficiary((string) Str::uuid7(), 0)]);
        $stored = $repository->record($first);
        $retry = ActivityDistribution::resolve((string) Str::uuid7(), $moduleId, 'source', (string) Str::uuid7(), CarbonImmutable::now('UTC'), []);
        self::assertSame($stored->id, $repository->record($retry)->id);
        self::assertCount(1, $stored->beneficiaries);
    }
}
