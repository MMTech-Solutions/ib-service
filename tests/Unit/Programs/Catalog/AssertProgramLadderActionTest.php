<?php

declare(strict_types=1);

namespace Tests\Unit\Programs\Catalog;

use App\Features\Programs\Catalog\Actions\AssertProgramLadderAction;
use App\Features\Programs\Catalog\Exceptions\ProgramLadderInvalidException;
use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class AssertProgramLadderActionTest extends TestCase
{
    public function test_it_accepts_a_strictly_increasing_ladder(): void
    {
        $action = new AssertProgramLadderAction;
        $action->assert([
            $this->program(1, 0),
            $this->program(2, 100),
            $this->program(3, 500),
        ]);
        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_duplicate_or_decreasing_thresholds(): void
    {
        $action = new AssertProgramLadderAction;
        $this->expectException(ProgramLadderInvalidException::class);
        $action->assert([
            $this->program(1, 0),
            $this->program(2, 0),
        ]);
    }

    private function program(int $position, int $entryThreshold): Program
    {
        return Program::create(
            id: (string) Str::uuid7(),
            planId: (string) Str::uuid7(),
            code: 'p'.$position,
            name: 'P'.$position,
            description: null,
            position: $position,
            entryThreshold: $entryThreshold,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: CarbonImmutable::now('UTC')->toISOString(),
        );
    }
}
