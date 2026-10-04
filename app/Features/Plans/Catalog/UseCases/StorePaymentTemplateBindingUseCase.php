<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\StorePaymentTemplateBindingResultData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Exceptions\TemplateBindingException;
use App\Features\Plans\Catalog\Factories\PaymentTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\StorePaymentTemplateBindingCommand;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateVersionPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StorePaymentTemplateBindingUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly PaymentTemplateBindingRepositoryFactory $bindings, private readonly ResolvePaymentTemplateVersionPort $versions) {}

    public function execute(StorePaymentTemplateBindingCommand $command): StorePaymentTemplateBindingResultData
    {
        $plans = $this->plans->make();

        return $plans->transaction(function () use ($plans, $command): StorePaymentTemplateBindingResultData {
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

            return $this->bindings->make()->createOrFind(new PaymentTemplateBindingData((string) Str::uuid7(), $command->planId, $version->id, CarbonImmutable::now('UTC')->toISOString()));
        });
    }
}
