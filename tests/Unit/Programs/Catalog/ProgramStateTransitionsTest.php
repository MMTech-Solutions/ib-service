<?php

declare(strict_types=1);

namespace Tests\Unit\Programs\Catalog;

use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class ProgramStateTransitionsTest extends TestCase
{
    public function test_it_replaces_selections_and_ignores_noop_updates(): void
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $moduleA = (string) Str::uuid7();
        $moduleB = (string) Str::uuid7();
        $program = Program::create(
            id: (string) Str::uuid7(),
            planId: (string) Str::uuid7(),
            code: 'basic',
            name: 'Basic',
            description: null,
            position: 1,
            moduleIds: [$moduleA],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );

        self::assertFalse($program->updateAdministrativeFields('Basic', null, $now));
        self::assertTrue($program->updateAdministrativeFields('Basic Plus', 'Updated', $now));
        self::assertFalse($program->replaceSelections([$moduleA], static fn (): string => (string) Str::uuid7(), $now));
        self::assertTrue($program->replaceSelections([$moduleA, $moduleB], static fn (): string => (string) Str::uuid7(), $now));
        self::assertSame([$moduleA, $moduleB], $program->moduleIds());
        self::assertTrue($program->assignPosition(2, $now));
        self::assertFalse($program->assignPosition(2, $now));
    }
}
