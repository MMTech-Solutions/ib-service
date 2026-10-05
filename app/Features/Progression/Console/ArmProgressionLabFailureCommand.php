<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\Factories\LabFailureRepositoryFactory;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ArmProgressionLabFailureCommand extends Command
{
    protected $signature = 'progression:lab-arm-failure {--id=} {--context=} {--operation=} {--stage=} {--subscription=} {--result=}';

    protected $description = 'Arm a durable one-shot Progression failure in local Lab only';

    public function handle(DomainClock $clock, LabFailureRepositoryFactory $repositoryFactory): int
    {
        try {
            $clock->assertLab();
            if (! config('lab.failures_enabled')) {
                throw new \RuntimeException('Lab failures disabled.');
            }
            $clock->begin();
            $id = (string) $this->option('id');
            $context = (string) $this->option('context');
            $operation = (string) $this->option('operation');
            $stage = (string) $this->option('stage');
            $subscription = $this->option('subscription');
            $result = $this->option('result');
            if (! Str::isUuid($id) || ! Str::isUuid($context) || $clock->context()['context_id'] !== $context ||
                ! in_array($operation, ['close', 'recover'], true) || ! in_array($stage, ['before_result_finalize', 'before_placement'], true) ||
                (($subscription === null) === ($result === null)) || ! Str::isUuid((string) ($subscription ?? $result))) {
                throw new \RuntimeException('Invalid failure selector.');
            }
            $repositoryFactory->make()->arm($id, $context, $operation, $stage, $subscription !== null ? 'subscription' : 'result', (string) ($subscription ?? $result));
            $this->line('Lab failure armed.');

            return 0;
        } catch (\Throwable) {
            $this->getOutput()->getErrorStyle()->writeln('Lab failure rejected.');

            return 2;
        } finally {
            $clock->end();
        }
    }
}
