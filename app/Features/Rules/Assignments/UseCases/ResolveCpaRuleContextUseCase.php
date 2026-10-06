<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Factories\CpaConfigurationRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use App\Features\Rules\Contracts\Data\V1\ResolveCpaRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveCpaRuleContextPort;

final class ResolveCpaRuleContextUseCase implements ResolveCpaRuleContextPort
{
    public function __construct(private readonly CpaConfigurationRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolveCpaRuleContextQueryData $query): CpaRuleContextData
    {
        return $this->repositoryFactory->make()->resolve($query->program_id, $query->occurred_at);
    }
}
