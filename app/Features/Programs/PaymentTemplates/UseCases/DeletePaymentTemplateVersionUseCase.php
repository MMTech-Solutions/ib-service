<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateVersionAction;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;

final class DeletePaymentTemplateVersionUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly ResolvePaymentTemplateVersionAction $resolveVersion,
    ) {}

    public function execute(string $id, string $versionId, int $lockVersion): void
    {
        $template = $this->resolveTemplate->execute($id);
        $version = $this->resolveVersion->execute($template, $versionId);
        if (! $version->isDraft()) {
            throw PaymentTemplateException::immutable($versionId);
        }

        $this->repositoryFactory->make()->deleteVersion($version, $lockVersion);
    }
}
