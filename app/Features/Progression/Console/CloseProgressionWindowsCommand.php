<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\UseCases\CloseProgressionWindowsUseCase;
use Illuminate\Console\Command;

final class CloseProgressionWindowsCommand extends Command
{
    protected $signature = 'progression:close-windows {--json}';

    protected $description = 'Close due Progression windows and apply pending placements';

    public function handle(ProgressionCommandRunner $runner, CloseProgressionWindowsUseCase $useCase): int
    {
        return $runner->execute($this, 'close', static function (): void {}, static fn (): array => ['outcome' => 'processed', 'counts' => $useCase->execute()->toArray()]);
    }
}
