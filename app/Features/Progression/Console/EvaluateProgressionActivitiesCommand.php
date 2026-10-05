<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\DTOs\EvaluateProgressionActivitiesData;
use App\Features\Progression\UseCases\EvaluateProgressionActivitiesUseCase;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class EvaluateProgressionActivitiesCommand extends Command
{
    protected $signature = 'progression:evaluate-activities {--plan=} {--module=} {--from=} {--until=} {--limit=100} {--resuming-after-pause} {--json}';

    protected $description = 'Evaluate all activity pages for a Progression plan and module';

    public function handle(ProgressionCommandRunner $runner, EvaluateProgressionActivitiesUseCase $useCase): int
    {
        return $runner->execute($this, 'evaluate', function (): void {
            foreach (['plan', 'module'] as $key) {
                if (! Str::isUuid((string) $this->option($key))) {
                    throw new \InvalidArgumentException;
                }
            }
            foreach (['from', 'until'] as $key) {
                if (! DomainClock::validUtc($this->option($key))) {
                    throw new \InvalidArgumentException;
                }
            }
            $limit = (string) $this->option('limit');
            if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100 ||
                ! CarbonImmutable::parse($this->option('from'))->lessThan(CarbonImmutable::parse($this->option('until')))) {
                throw new \InvalidArgumentException;
            }
        }, function () use ($useCase): array {
            $result = $useCase->execute(new EvaluateProgressionActivitiesData(
                (string) $this->option('plan'), (string) $this->option('module'), (string) $this->option('from'), (string) $this->option('until'),
                (bool) $this->option('resuming-after-pause'), (int) $this->option('limit'),
            ));
            $accepted = count(array_filter($result->evaluations, static fn ($evaluation): bool => $evaluation->status->value === 'accepted'));

            return ['outcome' => $result->outcome, 'counts' => ['evaluations' => count($result->evaluations), 'accepted' => $accepted,
                'excluded' => count($result->evaluations) - $accepted, 'retryable_failures' => count($result->retryableFailures)]];
        });
    }
}
