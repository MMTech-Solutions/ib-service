<?php

declare(strict_types=1);

namespace App\Providers;

use App\SharedFeatures\Clock\Console\LabClockAdvanceCommand;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ClockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DomainClock::class);
    }

    public function boot(): void
    {
        Event::listen(CommandStarting::class, function ($event): void {
            if ($event->command === 'modules:sync' || (str_starts_with((string) $event->command, 'rewards:'))) {
                $clock = $this->app->make(DomainClock::class);
                $clock->end();
                $clock->begin();
            }
        });
        Event::listen(JobProcessing::class, function (): void {
            $clock = $this->app->make(DomainClock::class);
            $clock->end();
            $clock->begin();
        });
        foreach ([JobProcessed::class, JobExceptionOccurred::class] as $event) {
            Event::listen($event, fn () => $this->app->make(DomainClock::class)->end());
        }
        Event::listen(CommandFinished::class, fn () => $this->app->make(DomainClock::class)->end());
        $this->commands([LabClockAdvanceCommand::class]);
    }
}
