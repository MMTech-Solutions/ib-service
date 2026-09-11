<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Actions;

use App\Features\Modules\Catalog\DTOs\ModuleDetailData;
use App\Features\Modules\Catalog\Enums\OperationalControlAction;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\OperationalControlChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ApplyModuleOperationalChangeAction
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(
        string $moduleId,
        int $expectedLockVersion,
        OperationalControlAction $action,
        string $actorIamId,
        string $reason,
    ): ModuleDetailData {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use (
            $repository,
            $moduleId,
            $expectedLockVersion,
            $action,
            $actorIamId,
            $reason,
        ): ModuleDetailData {
            $module = $repository->findById($moduleId);
            if ($module === null) {
                throw ModuleNotFoundException::forId($moduleId);
            }

            if ($module->lockVersion !== $expectedLockVersion) {
                throw ModuleConcurrencyException::forModule($moduleId);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $previousIsActive = $module->isActive;
            $previousProcessingStatus = $module->processingStatus;
            $changed = $this->apply($module, $action, $now);

            if (! $changed) {
                return $module->toDetailData();
            }

            $repository->update($module, $expectedLockVersion);
            $repository->appendOperationalChange(new OperationalControlChange(
                id: (string) Str::uuid7(),
                moduleId: $module->id,
                action: $action,
                actorIamId: $actorIamId,
                reason: $reason,
                previousIsActive: $previousIsActive,
                previousProcessingStatus: $previousProcessingStatus,
                nextIsActive: $module->isActive,
                nextProcessingStatus: $module->processingStatus,
                occurredAt: $now,
            ));

            return $module->toDetailData();
        });
    }

    private function apply(Module $module, OperationalControlAction $action, string $now): bool
    {
        return match ($action) {
            OperationalControlAction::Activate => $module->activate($now),
            OperationalControlAction::Deactivate => $module->deactivate($now),
            OperationalControlAction::Pause => $module->pause($now),
            OperationalControlAction::Resume => $module->resume($now),
        };
    }
}
