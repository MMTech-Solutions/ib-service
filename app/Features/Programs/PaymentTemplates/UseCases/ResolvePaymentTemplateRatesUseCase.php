<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\Contracts\Data\V1\NegativePnlPaymentLevelData;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateRatesPort;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;

final class ResolvePaymentTemplateRatesUseCase implements ResolvePaymentTemplateRatesPort
{
    public function __construct(private readonly PaymentTemplateRepositoryFactory $repositories) {}

    public function execute(string $versionId): ?array
    {
        $version = $this->repositories->make()->findVersion($versionId);
        if ($version?->status === 'published') {
            return array_map(static fn ($level): NegativePnlPaymentLevelData => new NegativePnlPaymentLevelData($level->distributionLevel, $level->rate), $version->levels);
        }

        return null;
    }
}
