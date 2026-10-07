<?php

declare(strict_types=1);

namespace App\Features\Rewards\Console;

use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:verify-cpa {--limit= : Maximum CPA contexts to evaluate}')]
#[Description('Verify pending CPA contexts, expire overdue ones, and create qualified pending rewards')]
final class VerifyCpaContextsCommand extends Command
{
    public function __construct(private readonly VerifyCpaContextsUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.cpa.batch_size']);
        $limit = $this->option('limit') === null ? (int) $settings->get('rewards.cpa.batch_size') : (int) $this->option('limit');
        $this->table(['Metric', 'Count'], collect($this->useCase->execute(max($limit, 1)))
            ->map(static fn (int $value, string $key): array => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
