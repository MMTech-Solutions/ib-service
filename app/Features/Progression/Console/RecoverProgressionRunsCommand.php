<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\UseCases\RecoverProgressionRunsUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RecoverProgressionRunsCommand extends Command
{
    protected $signature = 'progression:recover-runs {--run=} {--json}';

    protected $description = 'Recover failed Progression results and pending placements';

    public function handle(ProgressionCommandRunner $runner, RecoverProgressionRunsUseCase $useCase): int
    {
        return $runner->execute($this, 'recover', function (): void {
            if ($this->option('run') !== null && ! Str::isUuid((string) $this->option('run'))) {
                throw new \InvalidArgumentException;
            }
        }, fn (): array => ['outcome' => 'processed', 'counts' => $useCase->execute($this->option('run'))->toArray()]);
    }
}
