<?php

declare(strict_types=1);

namespace App\Features\Rewards\Console;

use App\Features\Rewards\UseCases\ProcessVolumeRewardsUseCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:process-volume {--limit= : Maximum receipts and periodic activities per execution}')]
#[Description('Process event and periodic traded-volume rewards')]
final class ProcessVolumeRewardsCommand extends Command
{
    public function __construct(private readonly ProcessVolumeRewardsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') === null
            ? (int) config('rewards.volume.batch_size', 100)
            : (int) $this->option('limit');
        $this->table(
            ['Metric', 'Count'],
            collect($this->useCase->execute(max($limit, 1)))
                ->map(static fn (int $value, string $key): array => [$key, $value])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }
}
