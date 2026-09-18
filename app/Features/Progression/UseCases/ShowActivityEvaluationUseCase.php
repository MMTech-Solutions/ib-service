<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Actions\PresentActivityEvaluationAction;
use App\Features\Progression\DTOs\ActivityEvaluationData;
use App\Features\Progression\Exceptions\ActivityEvaluationNotFoundException;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Http\V1\Commands\ShowActivityEvaluationCommand;

final class ShowActivityEvaluationUseCase
{
    public function __construct(
        private readonly ActivityEvaluationRepositoryFactory $repositoryFactory,
        private readonly PresentActivityEvaluationAction $presentActivityEvaluation,
    ) {}

    public function execute(ShowActivityEvaluationCommand $command): ActivityEvaluationData
    {
        $evaluation = $this->repositoryFactory->make()->findById($command->activityEvaluationId);
        if ($evaluation === null) {
            throw ActivityEvaluationNotFoundException::forId($command->activityEvaluationId);
        }

        return $this->presentActivityEvaluation->toData($evaluation);
    }
}
