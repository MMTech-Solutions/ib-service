<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Assignments;

use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentInactiveException;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class RuleAssignmentStateTransitionsTest extends TestCase
{
    public function test_it_activates_with_all_scope_and_can_withdraw(): void
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $assignment = RuleAssignment::activate(
            id: (string) Str::uuid7(),
            ruleId: (string) Str::uuid7(),
            ruleVersionId: (string) Str::uuid7(),
            programId: (string) Str::uuid7(),
            moduleId: (string) Str::uuid7(),
            now: $now,
        );

        self::assertTrue($assignment->isActive());
        self::assertSame(RuleAssignmentScopeType::All, $assignment->scopeType);
        self::assertNull($assignment->endsAt);

        $later = CarbonImmutable::now('UTC')->addMinute()->toISOString();
        $assignment->withdraw($later);
        self::assertFalse($assignment->isActive());
        self::assertSame($later, $assignment->endsAt);

        $this->expectException(RuleAssignmentInactiveException::class);
        $assignment->withdraw($later);
    }
}
