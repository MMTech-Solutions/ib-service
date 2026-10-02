<?php

declare(strict_types=1);

namespace App\Features\Rewards\Console;

use App\Features\Rewards\UseCases\ReconcileRewardSettlementsUseCase;
use Illuminate\Console\Command;

final class ReconcileRewardSettlementsCommand extends Command
{
    protected $signature = 'rewards:reconcile-settlements {--limit=}';

    protected $description = 'Reconcile only uncertain Reward financial operations with Finance.';

    public function __construct(private readonly ReconcileRewardSettlementsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') === null ? (int) config('rewards.reconciliation.batch_size', 100) : (int) $this->option('limit');
        $this->table(['Metric', 'Count'], collect($this->useCase->execute(max($limit, 1)))->map(static fn (int $value, string $key): array => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
