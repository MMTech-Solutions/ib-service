<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;

final class DeletePaymentTemplateUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
    ) {}

    public function execute(string $id, int $lockVersion): void
    {
        $this->repositoryFactory->make()->delete($this->resolveTemplate->execute($id), $lockVersion);
    }
}
