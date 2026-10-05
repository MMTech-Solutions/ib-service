<?php

declare(strict_types=1);

namespace App\Features\Progression\Services\Adapters;

use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\Factories\LabFailureRepositoryFactory;
use App\SharedFeatures\Clock\DomainClock;

final class LabProgressionFailureAdapter implements ProgressionFailurePort
{
    public function __construct(private readonly DomainClock $clock, private readonly LabFailureRepositoryFactory $repositoryFactory) {}

    public function check(string $operation, string $stage, string $subscriptionId, string $resultId): void
    {
        if (! config('lab.failures_enabled')) {
            return;
        }
        $this->clock->assertLab();
        $context = $this->clock->context();
        if ($context['context_id'] === null) {
            throw new \RuntimeException('Lab failures require controlled clock context.');
        }
        if ($this->repositoryFactory->make()->consume($context['context_id'], $operation, $stage, $subscriptionId, $resultId)) {
            throw new \RuntimeException('Lab one-shot failure.');
        }
    }
}
