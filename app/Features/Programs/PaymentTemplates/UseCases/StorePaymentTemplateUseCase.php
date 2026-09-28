<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StorePaymentTemplateUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $template = new PaymentTemplate((string) Str::uuid7(), $command->name ?? '', $command->description, 1, [], $now, $now);
        $this->repositoryFactory->make()->save($template);

        return $this->transform->execute($template);
    }
}
