<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\DTOs\SyncModulesResultData;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Events\V1\ModuleDeactivated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class SyncModulesUseCase
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly ModuleDefinitionRegistry $registry,
    ) {}

    public function execute(bool $prune = false): SyncModulesResultData
    {
        $repository = $this->repositoryFactory->make();

        $deactivatedModules = [];

        $result = $repository->transaction(function () use ($repository, $prune, &$deactivatedModules): SyncModulesResultData {
            $storedByCode = [];
            foreach ($repository->all() as $module) {
                $storedByCode[$module->code] = $module;
            }

            $created = 0;
            $updated = 0;
            $deactivated = 0;
            $capabilitiesActivated = 0;
            $capabilitiesDeactivated = 0;
            $pruned = 0;
            $protected = 0;

            foreach ($this->registry->definitions() as $definition) {
                $module = $storedByCode[$definition->code] ?? null;
                $now = CarbonImmutable::now('UTC')->toISOString();

                if ($module === null) {
                    $module = Module::fromDefinition(
                        id: (string) Str::uuid7(),
                        definition: $definition,
                        generateId: static fn (): string => (string) Str::uuid7(),
                        now: $now,
                    );
                    $repository->create($module);
                    $created++;
                    $capabilitiesActivated += count($definition->capabilities);
                } else {
                    $expectedLockVersion = $module->lockVersion;
                    $changes = $module->reconcileCapabilities(
                        definition: $definition,
                        generateId: static fn (): string => (string) Str::uuid7(),
                        now: $now,
                    );
                    if ($changes['changed']) {
                        $repository->update($module, $expectedLockVersion);
                        $updated++;
                        $capabilitiesActivated += $changes['activated'];
                        $capabilitiesDeactivated += $changes['deactivated'];
                    }
                }

                unset($storedByCode[$definition->code]);
            }

            foreach ($storedByCode as $module) {
                if ($prune && $repository->deleteIfUnreferenced($module)) {
                    $pruned++;

                    continue;
                }

                if ($prune) {
                    $protected++;
                }

                $expectedLockVersion = $module->lockVersion;
                $now = CarbonImmutable::now('UTC')->toISOString();
                if ($module->deactivate($now)) {
                    $repository->update($module, $expectedLockVersion);
                    $updated++;
                    $deactivated++;
                    $deactivatedModules[] = ['id' => $module->id, 'occurredAt' => $now];
                }
            }

            return new SyncModulesResultData(
                created: $created,
                updated: $updated,
                deactivated: $deactivated,
                capabilitiesActivated: $capabilitiesActivated,
                capabilitiesDeactivated: $capabilitiesDeactivated,
                pruned: $pruned,
                protected: $protected,
            );
        });

        foreach ($deactivatedModules as $deactivatedModule) {
            event(new ModuleDeactivated(
                eventId: (string) Str::uuid7(),
                moduleId: $deactivatedModule['id'],
                actorIamId: null,
                occurredAt: $deactivatedModule['occurredAt'],
            ));
        }

        return $result;
    }
}
