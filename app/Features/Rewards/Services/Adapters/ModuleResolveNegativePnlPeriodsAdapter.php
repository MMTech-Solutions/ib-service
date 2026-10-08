<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\UnsupportedNegativePnlPeriodsProviderException;
use App\Features\Rewards\Factories\NegativePnlPeriodsProviderFactory;

final class ModuleResolveNegativePnlPeriodsAdapter implements ResolveNegativePnlPeriodsPort
{
    public function __construct(private readonly ResolveModulesPort $modules, private readonly NegativePnlPeriodsProviderFactory $providers) {}

    public function resolve(ResolveNegativePnlPeriodsQueryData $query): ResolveNegativePnlPeriodsResultData
    {
        $code = $query->module_id === null ? 'broker' : ($this->modules->findByIds([$query->module_id])[0]->code ?? null);
        if ($code === null) {
            throw new UnsupportedNegativePnlPeriodsProviderException('unknown');
        }

        return $this->providers->make($code)->resolve($query);
    }
}
