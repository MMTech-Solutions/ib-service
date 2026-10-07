<?php

declare(strict_types=1);

namespace App\Features\Rewards\Console;

use App\Features\Rewards\UseCases\SettlePendingRewardsUseCase;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:settle-pending {--limit= : Maximum CPA rewards to settle}')]
#[Description('Settle pending CPA rewards through Finance')]
final class SettlePendingRewardsCommand extends Command
{
    public function __construct(private readonly SettlePendingRewardsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.settlement.batch_size']);
        $limit = $this->option('limit') === null ? (int) $settings->get('rewards.settlement.batch_size') : (int) $this->option('limit');
        $this->table(['Metric', 'Count'], collect($this->useCase->execute(max($limit, 1)))
            ->map(static fn (int $value, string $key): array => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
