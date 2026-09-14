<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Programs\Catalog\Actions\PresentProgramAction;
use App\Features\Programs\Catalog\DTOs\ProgramDetailData;
use App\Features\Programs\Catalog\Exceptions\ProgramNotFoundException;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ShowProgramCommand;

final class ShowProgramUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
        private readonly PresentProgramAction $presentProgram,
    ) {}

    public function execute(ShowProgramCommand $command): ProgramDetailData
    {
        $this->plans->resolve($command->planId);
        $program = $this->repositoryFactory->make()->findByPlanAndId($command->planId, $command->programId);
        if ($program === null) {
            throw ProgramNotFoundException::forId($command->programId);
        }

        return $this->presentProgram->toDetail($program);
    }
}
