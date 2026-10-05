<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\DTOs\ProgressionReadPageData;
use App\Features\Progression\Exceptions\ProgressionArtifactNotFoundException;
use App\Features\Progression\Factories\ProgressionReadRepositoryFactory;
use App\Features\Progression\Http\V1\Commands\ReadProgressionCommand;

final class ReadProgressionUseCase
{
    public function __construct(private readonly ProgressionReadRepositoryFactory $repositoryFactory) {}

    public function execute(ReadProgressionCommand $command): ProgressionReadPageData
    {
        $result = $this->repositoryFactory->make()->read($command->query);
        if ($command->query->id !== null && $result->items === []) {
            throw ProgressionArtifactNotFoundException::missing();
        }

        return $result;
    }
}
