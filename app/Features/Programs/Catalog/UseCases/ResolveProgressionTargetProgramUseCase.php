<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\ProgressionTargetProgramData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgressionTargetProgramQueryData;
use App\Features\Programs\Contracts\Exceptions\ProgramNotAvailableForPlanException;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTargetProgramPort;

final class ResolveProgressionTargetProgramUseCase implements ResolveProgressionTargetProgramPort
{
    public function __construct(private readonly ProgramRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolveProgressionTargetProgramQueryData $query): ProgressionTargetProgramData
    {
        $programs = $this->repositoryFactory->make()->listByPlanId($query->plan_id);
        $target = null;

        foreach ($programs as $program) {
            if ($this->isAtLeastThreshold($query->total_points, $program->entryThreshold)) {
                $target = $program;
            }
        }

        if ($target === null) {
            throw ProgramNotAvailableForPlanException::forPlan($query->plan_id);
        }

        return new ProgressionTargetProgramData(program_id: $target->id);
    }

    private function isAtLeastThreshold(string $points, int $threshold): bool
    {
        [$integer] = explode('.', ltrim($points, '+'), 2);
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $thresholdValue = (string) $threshold;

        return strlen($integer) > strlen($thresholdValue)
            || (strlen($integer) === strlen($thresholdValue) && strcmp($integer, $thresholdValue) >= 0);
    }
}
