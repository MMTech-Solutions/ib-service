<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Actions\PresentActivityEvaluationAction;
use App\Features\Progression\DTOs\ActivityEvaluationsPageData;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Http\V1\Commands\ListActivityEvaluationsCommand;
use App\Features\Progression\Models\ActivityEvaluation;

final class ListActivityEvaluationsUseCase
{
    public function __construct(
        private readonly ActivityEvaluationRepositoryFactory $repositoryFactory,
        private readonly PresentActivityEvaluationAction $presentActivityEvaluation,
    ) {}

    public function execute(ListActivityEvaluationsCommand $command): ActivityEvaluationsPageData
    {
        $page = $this->repositoryFactory->make()->paginate($command->toQueryData());

        return new ActivityEvaluationsPageData(
            evaluations: array_map(
                fn (ActivityEvaluation $evaluation) => $this->presentActivityEvaluation->toData($evaluation),
                $page->evaluations,
            ),
            currentPage: $page->currentPage,
            perPage: $page->perPage,
            total: $page->total,
            lastPage: $page->lastPage,
        );
    }
}
