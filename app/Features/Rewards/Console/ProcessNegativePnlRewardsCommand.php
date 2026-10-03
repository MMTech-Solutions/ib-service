<?php

declare(strict_types=1);

namespace App\Features\Rewards\Console;

use App\Features\Rewards\UseCases\ProcessNegativePnlRewardsUseCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:process-negative-pnl {--limit= : Maximum periods per execution} {--discovery-limit= : Maximum discovery items per execution}')]
#[Description('Recover due negative-PnL periods and create pending rewards')]
final class ProcessNegativePnlRewardsCommand extends Command
{
    public function __construct(private readonly ProcessNegativePnlRewardsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $metrics = $this->useCase->execute((int) ($this->option('limit') ?? config('rewards.negative_pnl.batch_size', 100)), (int) ($this->option('discovery-limit') ?? config('rewards.negative_pnl.discovery_batch_size', 100)));
        $this->table(['Metric', 'Count'], collect($metrics)->map(static fn (int $count, string $metric): array => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
