<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Modules\Catalog\Contracts\Data\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleDefinitionData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleListQueryData;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\Exceptions\DuplicateModuleCodeException;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Models\Module;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ModuleRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): ModuleRepositoryInterface;

    public function test_it_creates_recovers_and_paginates_a_module(): void
    {
        $repository = $this->repository();
        $module = $this->module('broker');
        $repository->create($module);

        $stored = $repository->findByCode('broker');
        self::assertNotNull($stored);
        self::assertSame($module->id, $stored->id);
        self::assertSame(['deposits'], array_map(static fn ($capability): string => $capability->code, $stored->capabilities));

        $page = $repository->paginate(new ModuleListQueryData(search: 'BROK'));
        self::assertSame(1, $page->total);
        self::assertSame('broker', $page->modules[0]->code);
    }

    public function test_it_rejects_duplicate_codes(): void
    {
        $repository = $this->repository();
        $repository->create($this->module('broker'));

        $this->expectException(DuplicateModuleCodeException::class);
        $repository->create($this->module('broker'));
    }

    public function test_it_rejects_an_obsolete_lock_version(): void
    {
        $repository = $this->repository();
        $repository->create($this->module('broker'));
        $firstWriter = $repository->findByCode('broker');
        $obsoleteWriter = $repository->findByCode('broker');
        self::assertNotNull($firstWriter);
        self::assertNotNull($obsoleteWriter);

        $firstWriter->deactivate(CarbonImmutable::now('UTC')->toISOString());
        $repository->update($firstWriter, 1);

        $obsoleteWriter->deactivate(CarbonImmutable::now('UTC')->toISOString());
        $this->expectException(ModuleConcurrencyException::class);
        $repository->update($obsoleteWriter, 1);
    }

    public function test_transaction_rolls_back_all_changes_after_an_error(): void
    {
        $repository = $this->repository();

        try {
            $repository->transaction(function () use ($repository): void {
                $repository->create($this->module('broker'));
                throw new \RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        self::assertNull($repository->findByCode('broker'));
    }

    protected function module(string $code): Module
    {
        $now = CarbonImmutable::now('UTC')->toISOString();

        return Module::fromDefinition(
            id: (string) Str::uuid7(),
            definition: new ModuleDefinitionData(
                code: $code,
                name: ucfirst($code),
                description: null,
                capabilities: [new ModuleCapabilityDefinitionData('deposits', 'Deposits', null)],
            ),
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
    }
}
