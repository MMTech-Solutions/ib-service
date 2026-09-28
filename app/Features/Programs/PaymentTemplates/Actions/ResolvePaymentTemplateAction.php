<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Actions;

use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;

final class ResolvePaymentTemplateAction
{
    public function __construct(private readonly PaymentTemplateRepositoryFactory $repositoryFactory) {}

    public function execute(string $id): PaymentTemplate
    {
        return $this->repositoryFactory->make()->find($id) ?? throw PaymentTemplateException::notFound($id);
    }
}
