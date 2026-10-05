<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\SharedFeatures\Clock\DomainClock;

final class UpdatePaymentTemplateUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $command): PaymentTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $template->updateDetails($command->name, $command->description, app(DomainClock::class)->now()->toISOString());
            $repository->save($template, $command->lockVersion);

            return $this->transform->execute($template);
        });
    }
}
