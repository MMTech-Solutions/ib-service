<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\BuildPaymentTemplateLevelsAction;
use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateVersionAction;
use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\SharedFeatures\Clock\DomainClock;

final class UpdatePaymentTemplateVersionUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly ResolvePaymentTemplateVersionAction $resolveVersion,
        private readonly BuildPaymentTemplateLevelsAction $buildLevels,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, string $versionId, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $versionId, $command): PaymentTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $version = $this->resolveVersion->execute($template, $versionId);
            if (! $version->isDraft()) {
                throw PaymentTemplateException::immutable($versionId);
            }

            $version->replaceLevels($this->buildLevels->execute($command->levels ?? []), app(DomainClock::class)->now()->toISOString());
            $repository->saveVersion($version, false, $command->lockVersion);

            return $this->transform->execute($template);
        });
    }
}
