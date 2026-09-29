<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\UseCases\CloseProgressionWindowsUseCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('progression:close-windows')]
#[Description('Close due Progression windows and retry failed run results')]
final class CloseProgressionWindowsCommand extends Command
{
    public function __construct(private readonly CloseProgressionWindowsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->useCase->execute();
        } catch (Throwable $throwable) {
            report($throwable);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
        $this->table(['Metric', 'Count'], collect($result->toArray())->map(fn (mixed $count, string $metric): array => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
