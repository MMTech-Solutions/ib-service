<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class TemplateCatalogRefactorTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_payment_template_version_can_be_published_and_is_immutable_afterwards(): void
    {
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/payment-templates', [
            'name' => 'Standard payment',
        ])->assertCreated()->json('data');

        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template['id']}/versions", [
            'levels' => [['distribution_level' => 0, 'rate' => '12.5']],
        ])->assertCreated()->json('data.versions.0');

        $published = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/payment-templates/{$template['id']}/versions/{$version['id']}/publish",
            ['lock_version' => $version['lock_version']],
        )->assertOk()->assertJsonPath('data.versions.0.status', 'published')->json('data.versions.0');

        $this->gatewayJson(
            'PATCH',
            "/api/ib/v1/admin/payment-templates/{$template['id']}/versions/{$version['id']}",
            ['levels' => [['distribution_level' => 0, 'rate' => '13']], 'lock_version' => $published['lock_version']],
        )->assertConflict()->assertJsonPath('error.code', 'PAYMENT_TEMPLATE_VERSION_IMMUTABLE');
    }

    public function test_progression_template_version_rejects_a_stale_lock_version(): void
    {
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/progression-templates', [
            'name' => 'Standard progression',
        ])->assertCreated()->json('data');

        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/progression-templates/{$template['id']}/versions", [
            'levels' => [['distribution_level' => 0, 'weight' => '1.5']],
        ])->assertCreated()->json('data.versions.0');

        $this->gatewayJson(
            'PATCH',
            "/api/ib/v1/admin/progression-templates/{$template['id']}/versions/{$version['id']}",
            ['levels' => [['distribution_level' => 0, 'weight' => '2']], 'lock_version' => $version['lock_version'] + 1],
        )->assertConflict()->assertJsonPath('error.code', 'PROGRESSION_TEMPLATE_CONCURRENCY_CONFLICT');
    }

    public function test_every_template_controller_declares_its_specific_use_case(): void
    {
        $routeNames = [
            'ib.v1.admin.payment-templates.index',
            'ib.v1.admin.payment-templates.store',
            'ib.v1.admin.payment-templates.show',
            'ib.v1.admin.payment-templates.update',
            'ib.v1.admin.payment-templates.destroy',
            'ib.v1.admin.payment-templates.versions.store',
            'ib.v1.admin.payment-templates.versions.update',
            'ib.v1.admin.payment-templates.versions.publish',
            'ib.v1.admin.payment-templates.versions.destroy',
            'ib.v1.admin.progression-templates.index',
            'ib.v1.admin.progression-templates.store',
            'ib.v1.admin.progression-templates.show',
            'ib.v1.admin.progression-templates.update',
            'ib.v1.admin.progression-templates.destroy',
            'ib.v1.admin.progression-templates.versions.store',
            'ib.v1.admin.progression-templates.versions.update',
            'ib.v1.admin.progression-templates.versions.publish',
            'ib.v1.admin.progression-templates.versions.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            self::assertNotNull($route);
            $controller = $route->getActionName();
            $reflection = new ReflectionMethod($controller, '__invoke');
            $parameters = $reflection->getParameters();
            $useCase = $parameters[array_key_last($parameters)]->getType();

            self::assertNotNull($useCase);
            self::assertSame(
                str_replace('Controller', 'UseCase', str_replace('\\Http\\V1\\Controllers\\', '\\UseCases\\', $controller)),
                $useCase->getName(),
                $routeName,
            );
        }
    }
}
