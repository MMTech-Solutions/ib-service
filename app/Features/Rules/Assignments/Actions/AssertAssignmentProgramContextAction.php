<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgramContextData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;

final class AssertAssignmentProgramContextAction
{
    public function __construct(private readonly ResolveProgramContextPort $programs) {}

    public function assertSelectedModule(string $planId, string $programId, string $moduleId): ProgramContextData
    {
        return $this->programs->assertSelectedModule(new AssertSelectedModuleQueryData(
            plan_id: $planId,
            program_id: $programId,
            module_id: $moduleId,
        ));
    }
}
