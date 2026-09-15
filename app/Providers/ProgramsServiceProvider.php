<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use App\Features\Programs\Catalog\Repositories\PostgreSql\PostgreSqlProgramRepository;
use App\Features\Programs\Catalog\UseCases\ResolveProgramContextUseCase;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ProgramsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResolveProgramContextPort::class, ResolveProgramContextUseCase::class);
        $this->app->singleton(
            'programs.repositories.memory',
            fn (): InMemoryProgramRepository => new InMemoryProgramRepository,
        );
        $this->app->singleton(
            'programs.repositories.postgresql',
            fn (Application $app): PostgreSqlProgramRepository => new PostgreSqlProgramRepository(
                DB::connection(),
            ),
        );
    }
}
