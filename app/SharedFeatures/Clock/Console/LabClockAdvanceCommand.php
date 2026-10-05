<?php

declare(strict_types=1);

namespace App\SharedFeatures\Clock\Console;

use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Console\Command;

final class LabClockAdvanceCommand extends Command
{
    protected $signature = 'ib-lab:clock {--context=} {--sequence=} {--now=}';

    protected $description = 'Atomically advance the private Lab domain clock';

    public function handle(DomainClock $clock): int
    {
        try {
            $sequence = $this->option('sequence');
            if (! is_string($sequence) || ! ctype_digit($sequence) || strlen($sequence) > 18) {
                throw new \RuntimeException('Invalid sequence.');
            }
            $clock->advance((string) $this->option('context'), (int) $sequence, (string) $this->option('now'));
            $this->line('Lab clock advanced.');

            return 0;
        } catch (\Throwable) {
            $this->getOutput()->getErrorStyle()->writeln('Lab clock advance rejected.');

            return 2;
        }
    }
}
