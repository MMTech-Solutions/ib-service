<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\CpaEvidenceProviderFactory;
use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use Carbon\CarbonImmutable;

final class ListCpaEvidenceUseCase implements ListCpaEvidencePort
{
    public function __construct(
        private readonly ResolveModulesPort $modules,
        private readonly CpaEvidenceProviderFactory $providers,
    ) {}

    public function list(ListCpaEvidenceQueryData $query): CpaEvidenceData
    {
        $module = $this->modules->findByIds([$query->module_id])[0];
        if ($module->code !== 'broker') {
            throw InvalidProgressionActivityQueryException::withMessage('CPA evidence is unavailable for this module.');
        }

        $from = CarbonImmutable::parse($query->occurred_from)->utc();
        $until = CarbonImmutable::parse($query->occurred_until)->utc();
        if (! $from->lt($until)) {
            throw InvalidProgressionActivityQueryException::withMessage('CPA evidence requires a valid semi-open interval.');
        }

        return $this->providers->make($module->code)->fetch($query);
    }
}
