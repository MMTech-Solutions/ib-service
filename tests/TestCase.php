<?php

declare(strict_types=1);

namespace Tests;

use App\Features\Settings\Repositories\InMemory\InMemorySettingRepository;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->app->instance(
                SettingRepositoryInterface::class,
                new InMemorySettingRepository,
            );
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'pgsql_testing' || ! str_ends_with($database, '_testing')) {
            self::fail('Tests must use the isolated PostgreSQL database ending in _testing.');
        }
    }
}
