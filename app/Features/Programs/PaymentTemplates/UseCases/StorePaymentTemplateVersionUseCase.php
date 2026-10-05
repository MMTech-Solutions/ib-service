<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\BuildPaymentTemplateLevelsAction;
use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Support\Str;

final class StorePaymentTemplateVersionUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly BuildPaymentTemplateLevelsAction $buildLevels,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $command): PaymentTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $now = app(DomainClock::class)->now()->toISOString();
            $version = new PaymentTemplateVersion(
                (string) Str::uuid7(),
                $template->id,
                $template->nextVersionNumber(),
                'draft',
                null,
                1,
                $this->buildLevels->execute($command->levels ?? []),
                $now,
                $now,
            );
            $repository->saveVersion($version, true);

            return $this->transform->execute($this->resolveTemplate->execute($id));
        });
    }
}
