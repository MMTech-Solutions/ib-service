<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Programs\Catalog\Factories\NegativePnlConfigurationRepositoryFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class NegativePnlConfigurationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithAdminGateway;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    public function test_program_is_locked_even_before_the_first_configuration_exists(): void
    {
        $this->seedAuthorizedAdmin();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'concurrent', 'name' => 'Concurrent', 'module_ids' => [], 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/programs", ['code' => 'entry', 'name' => 'Entry', 'entry_threshold' => 0])->assertCreated()->json('data.id');
        config()->set('database.connections.pnl_concurrency', config('database.connections.pgsql_testing'));
        $competitor = DB::connection('pnl_concurrency');
        $competitor->statement("SET lock_timeout = '100ms'");
        try {
            app(NegativePnlConfigurationRepositoryFactory::class)->make()->transactionForProgram($program, function () use ($competitor, $program): void {
                self::assertSame(0, DB::table('program_negative_pnl_configuration_revisions')->count());
                try {
                    $competitor->select('SELECT id FROM programs WHERE id = ? FOR UPDATE', [$program]);
                    self::fail('A competing replacement must wait for the program lock.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0]);
                }
            });
            self::assertCount(1, $competitor->select('SELECT id FROM programs WHERE id = ? FOR UPDATE', [$program]));
        } finally {
            DB::purge('pnl_concurrency');
        }
    }
}
