<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Plans\Catalog\Exceptions\TemplateBindingException;
use App\Features\Plans\Catalog\Factories\PaymentTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Factories\ProgressionTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ArchivePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Commands\StorePaymentTemplateBindingCommand;
use App\Features\Plans\Catalog\Http\V1\Commands\StoreProgressionTemplateBindingCommand;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlPaymentTemplateBindingRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlPlanRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlProgressionTemplateBindingRepository;
use App\Features\Plans\Catalog\UseCases\ArchivePlanUseCase;
use App\Features\Plans\Catalog\UseCases\StorePaymentTemplateBindingUseCase;
use App\Features\Plans\Catalog\UseCases\StoreProgressionTemplateBindingUseCase;
use App\Features\Programs\Contracts\Data\V1\PaymentTemplateVersionIdentityData;
use App\Features\Programs\Contracts\Data\V1\ProgressionTemplateVersionIdentityData;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateVersionPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTemplateVersionPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class PlanTemplateBindingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithAdminGateway;

    protected function tearDown(): void
    {
        DB::purge('binding_competitor');
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    public function test_competing_creation_waits_and_reuses_committed_binding(): void
    {
        $this->seedAuthorizedAdmin();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'concurrent', 'name' => 'Concurrent', 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/payment-templates', ['name' => 'Concurrent'])->assertCreated()->json('data.id');
        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, 'rate' => '0.1']]])->assertCreated()->json('data.versions.0');
        $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions/{$version['id']}/publish", ['lock_version' => $version['lock_version']])->assertOk();
        config()->set('database.connections.binding_competitor', config('database.connections.pgsql_testing'));
        $connection = DB::connection('binding_competitor');
        $connection->statement("SET lock_timeout = '100ms'");
        $container = new Container;
        $container->instance('plans.repositories.postgresql', new PostgreSqlPlanRepository($connection));
        $container->instance('plans.payment-template-bindings.repositories.postgresql', new PostgreSqlPaymentTemplateBindingRepository($connection));
        $versions = app(ResolvePaymentTemplateVersionPort::class);
        $competitor = new StorePaymentTemplateBindingUseCase(new PlanRepositoryFactory($container), new PaymentTemplateBindingRepositoryFactory($container), $versions);
        $command = new StorePaymentTemplateBindingCommand($plan, $version['id']);
        $archive = new ArchivePlanUseCase(new PlanRepositoryFactory($container), app(HasOpenSubscriptionsForPlanPort::class));
        $archiveCommand = new ArchivePlanCommand($plan, 1, $this->authorizedSub(), 'Concurrent archive');
        $guard = new class($versions, $competitor, $command, $archive, $archiveCommand) implements ResolvePaymentTemplateVersionPort
        {
            public bool $blocked = false;

            public bool $archiveBlocked = false;

            public function __construct(private readonly ResolvePaymentTemplateVersionPort $versions, private readonly StorePaymentTemplateBindingUseCase $competitor, private readonly StorePaymentTemplateBindingCommand $command, private readonly ArchivePlanUseCase $archive, private readonly ArchivePlanCommand $archiveCommand) {}

            public function execute(string $versionId): ?PaymentTemplateVersionIdentityData
            {
                $defaultConnection = DB::getDefaultConnection();
                DB::setDefaultConnection('binding_competitor');
                try {
                    try {
                        $this->competitor->execute($this->command);
                    } catch (QueryException $exception) {
                        if ($exception->errorInfo[0] !== '55P03') {
                            throw $exception;
                        } $this->blocked = true;
                    }

                    try {
                        $this->archive->execute($this->archiveCommand);
                    } catch (QueryException $exception) {
                        if ($exception->errorInfo[0] !== '55P03') {
                            throw $exception;
                        }
                        $this->archiveBlocked = true;
                    }

                } finally {
                    DB::setDefaultConnection($defaultConnection);
                }

                return $this->versions->execute($versionId);
            }
        };
        $primary = new StorePaymentTemplateBindingUseCase(app(PlanRepositoryFactory::class), app(PaymentTemplateBindingRepositoryFactory::class), $guard);
        $first = $primary->execute($command);
        self::assertTrue($guard->blocked);
        self::assertTrue($guard->archiveBlocked);
        self::assertTrue($first->created);
        $repeat = $competitor->execute($command);
        self::assertFalse($repeat->created);
        self::assertSame($first->binding->toArray(), $repeat->binding->toArray());
        self::assertSame(1, $connection->table('plan_payment_template_version_bindings')->count());
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan}", ['lock_version' => 1, 'reason' => 'Retain history'])->assertNoContent();
        try {
            $competitor->execute($command);
            self::fail('An archived plan cannot accept repeated or new bindings.');
        } catch (TemplateBindingException $exception) {
            self::assertSame('PLAN_ARCHIVED', $exception->getErrorCode());
        }
    }

    public function test_progression_creation_waits_and_reuses_committed_binding(): void
    {
        $this->seedAuthorizedAdmin();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'concurrent', 'name' => 'Concurrent', 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/progression-templates', ['name' => 'Concurrent'])->assertCreated()->json('data.id');
        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/progression-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, 'weight' => '0.1']]])->assertCreated()->json('data.versions.0');
        $this->gatewayJson('POST', "/api/ib/v1/admin/progression-templates/{$template}/versions/{$version['id']}/publish", ['lock_version' => $version['lock_version']])->assertOk();
        config()->set('database.connections.binding_competitor', config('database.connections.pgsql_testing'));
        $connection = DB::connection('binding_competitor');
        $connection->statement("SET lock_timeout = '100ms'");
        $container = new Container;
        $container->instance('plans.repositories.postgresql', new PostgreSqlPlanRepository($connection));
        $container->instance('plans.progression-template-bindings.repositories.postgresql', new PostgreSqlProgressionTemplateBindingRepository($connection));
        $versions = app(ResolveProgressionTemplateVersionPort::class);
        $competitor = new StoreProgressionTemplateBindingUseCase(new PlanRepositoryFactory($container), new ProgressionTemplateBindingRepositoryFactory($container), $versions);
        $command = new StoreProgressionTemplateBindingCommand($plan, $version['id']);
        $archive = new ArchivePlanUseCase(new PlanRepositoryFactory($container), app(HasOpenSubscriptionsForPlanPort::class));
        $archiveCommand = new ArchivePlanCommand($plan, 1, $this->authorizedSub(), 'Concurrent archive');
        $guard = new class($versions, $competitor, $command, $archive, $archiveCommand) implements ResolveProgressionTemplateVersionPort
        {
            public bool $blocked = false;

            public bool $archiveBlocked = false;

            public function __construct(private readonly ResolveProgressionTemplateVersionPort $versions, private readonly StoreProgressionTemplateBindingUseCase $competitor, private readonly StoreProgressionTemplateBindingCommand $command, private readonly ArchivePlanUseCase $archive, private readonly ArchivePlanCommand $archiveCommand) {}

            public function execute(string $versionId): ?ProgressionTemplateVersionIdentityData
            {
                $defaultConnection = DB::getDefaultConnection();
                DB::setDefaultConnection('binding_competitor');
                try {
                    try {
                        $this->competitor->execute($this->command);
                    } catch (QueryException $exception) {
                        if ($exception->errorInfo[0] !== '55P03') {
                            throw $exception;
                        } $this->blocked = true;
                    }

                    try {
                        $this->archive->execute($this->archiveCommand);
                    } catch (QueryException $exception) {
                        if ($exception->errorInfo[0] !== '55P03') {
                            throw $exception;
                        }
                        $this->archiveBlocked = true;
                    }

                } finally {
                    DB::setDefaultConnection($defaultConnection);
                }

                return $this->versions->execute($versionId);
            }
        };
        $primary = new StoreProgressionTemplateBindingUseCase(app(PlanRepositoryFactory::class), app(ProgressionTemplateBindingRepositoryFactory::class), $guard);
        $first = $primary->execute($command);
        self::assertTrue($guard->blocked);
        self::assertTrue($guard->archiveBlocked);
        self::assertTrue($first->created);
        $repeat = $competitor->execute($command);
        self::assertFalse($repeat->created);
        self::assertSame($first->binding->toArray(), $repeat->binding->toArray());
        self::assertSame(1, $connection->table('plan_progression_template_version_bindings')->count());
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan}", ['lock_version' => 1, 'reason' => 'Retain history'])->assertNoContent();
        try {
            $competitor->execute($command);
            self::fail('An archived plan cannot accept repeated or new bindings.');
        } catch (TemplateBindingException $exception) {
            self::assertSame('PLAN_ARCHIVED', $exception->getErrorCode());
        }
    }
}
