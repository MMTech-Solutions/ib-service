<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\StoreProgressionTemplateBindingResultData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Exceptions\TemplateBindingException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Factories\ProgressionTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\StoreProgressionTemplateBindingCommand;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTemplateVersionPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreProgressionTemplateBindingUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly ProgressionTemplateBindingRepositoryFactory $bindings, private readonly ResolveProgressionTemplateVersionPort $versions) {}

    public function execute(StoreProgressionTemplateBindingCommand $command): StoreProgressionTemplateBindingResultData
    {
        $plans = $this->plans->make();

        return $plans->transaction(function () use ($plans, $command): StoreProgressionTemplateBindingResultData {
            $plans->lockAscending([$command->planId]);
            $plan = $plans->findByIdIncludingArchived($command->planId);
            if ($plan === null) {
                throw PlanNotFoundException::forId($command->planId);
            }
            if ($plan->deletedAt !== null) {
                throw TemplateBindingException::archived($command->planId);
            }
            $version = $this->versions->execute($command->templateVersionId);
            if ($version === null) {
                throw TemplateBindingException::versionNotFound($command->templateVersionId);
            }
            if ($version->status !== 'published') {
                throw TemplateBindingException::unpublished($command->templateVersionId);
            }

            return $this->bindings->make()->createOrFind(new ProgressionTemplateBindingData((string) Str::uuid7(), $command->planId, $version->id, CarbonImmutable::now('UTC')->toISOString()));
        });
    }
}
