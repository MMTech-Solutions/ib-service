<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Actions\ApplyModuleOperationalChangeAction;
use App\Features\Modules\Catalog\DTOs\ModuleDetailData;
use App\Features\Modules\Catalog\Enums\OperationalControlAction;
use App\Features\Modules\Catalog\Http\V1\Commands\ActivateModuleCommand;

final class ActivateModuleUseCase
{
    public function __construct(private readonly ApplyModuleOperationalChangeAction $applyOperationalChange) {}

    public function execute(ActivateModuleCommand $command): ModuleDetailData
    {
        return $this->applyOperationalChange->execute(
            moduleId: $command->moduleId,
            expectedLockVersion: $command->lockVersion,
            action: OperationalControlAction::Activate,
            actorIamId: $command->actorIamId,
            reason: $command->reason,
        );
    }
}
