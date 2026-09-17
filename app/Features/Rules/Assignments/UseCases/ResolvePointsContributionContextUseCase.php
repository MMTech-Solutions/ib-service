<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Actions\ResolvePointsContributionMatchesAction;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextResultData;
use App\Features\Rules\Contracts\Exceptions\AmbiguousPointsContributionRuleException;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;

final class ResolvePointsContributionContextUseCase implements ResolvePointsContributionContextPort
{
    public function __construct(
        private readonly ResolvePointsContributionMatchesAction $matches,
    ) {}

    public function resolve(ResolvePointsContributionContextQueryData $query): ResolvePointsContributionContextResultData
    {
        $matches = $this->matches->matchesAt(
            programId: $query->program_id,
            moduleId: $query->module_id,
            unitCode: $query->unit_code,
            occurredAt: $query->occurred_at,
        );

        $count = count($matches);
        if ($count === 0) {
            return ResolvePointsContributionContextResultData::absent();
        }

        if ($count > 1) {
            throw AmbiguousPointsContributionRuleException::forContext(
                $query->program_id,
                $query->module_id,
                $query->unit_code,
                $query->occurred_at,
            );
        }

        return ResolvePointsContributionContextResultData::foundContext($matches[0]);
    }
}
