<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateVersionAction;
use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use Carbon\CarbonImmutable;

final class PublishPaymentTemplateVersionUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly ResolvePaymentTemplateVersionAction $resolveVersion,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, string $versionId, int $lockVersion): PaymentTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $versionId, $lockVersion): PaymentTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $version = $this->resolveVersion->execute($template, $versionId);
            if (! $version->isDraft()) {
                throw PaymentTemplateException::immutable($versionId);
            }

            $version->publish(CarbonImmutable::now('UTC')->toISOString());
            $repository->saveVersion($version, false, $lockVersion);

            return $this->transform->execute($template);
        });
    }
}
