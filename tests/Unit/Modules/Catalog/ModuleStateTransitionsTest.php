<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Catalog;

use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class ModuleStateTransitionsTest extends TestCase
{
    public function test_operational_states_are_independent_and_idempotent(): void
    {
        $module = $this->module();

        self::assertTrue($module->pause('2026-09-11T10:00:00Z'));
        self::assertFalse($module->pause('2026-09-11T10:01:00Z'));
        self::assertTrue($module->deactivate('2026-09-11T10:02:00Z'));
        self::assertFalse($module->deactivate('2026-09-11T10:03:00Z'));
        self::assertFalse($module->isActive);
        self::assertSame(ProcessingStatus::Paused, $module->processingStatus);

        self::assertTrue($module->activate('2026-09-11T10:04:00Z'));
        self::assertTrue($module->resume('2026-09-11T10:05:00Z'));
        self::assertFalse($module->resume('2026-09-11T10:06:00Z'));
        self::assertTrue($module->isActive);
        self::assertSame(ProcessingStatus::Running, $module->processingStatus);
    }

    public function test_pause_and_resume_can_prepare_an_inactive_module_without_changing_availability(): void
    {
        $module = $this->module();
        $module->deactivate('2026-09-11T10:00:00Z');

        self::assertTrue($module->pause('2026-09-11T10:01:00Z'));
        self::assertFalse($module->isActive);
        self::assertSame(ProcessingStatus::Paused, $module->processingStatus);

        self::assertTrue($module->resume('2026-09-11T10:02:00Z'));
        self::assertFalse($module->isActive);
        self::assertSame(ProcessingStatus::Running, $module->processingStatus);
    }

    public function test_administrative_update_is_idempotent(): void
    {
        $module = $this->module();

        self::assertFalse($module->updateAdministrativeFields('Broker', null, '2026-09-11T10:00:00Z'));
        self::assertTrue($module->updateAdministrativeFields('Broker module', 'Description', '2026-09-11T10:01:00Z'));
        self::assertFalse($module->updateAdministrativeFields('Broker module', 'Description', '2026-09-11T10:02:00Z'));
    }

    private function module(): Module
    {
        return Module::fromDefinition(
            id: (string) Str::uuid7(),
            definition: new ModuleDefinitionData('broker', 'Broker', null, []),
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-09-11T09:00:00Z',
        );
    }
}
