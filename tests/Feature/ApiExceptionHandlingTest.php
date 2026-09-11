<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

final class ApiExceptionHandlingTest extends TestCase
{
    public function test_api_exceptions_are_rendered_as_normalized_http_errors(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $exception = ModuleNotFoundException::forId('missing-module');

        $this->assertInstanceOf(ApiException::class, $exception);

        $response = $handler->render(
            Request::create('/api/ib/v1/admin/modules/missing-module'),
            $exception,
        );

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('MODULE_NOT_FOUND', $payload['error']['code']);
    }

    public function test_only_api_exceptions_are_excluded_from_reporting(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertInstanceOf(Handler::class, $handler);
        $this->assertFalse($handler->shouldReport(ModuleNotFoundException::forId('missing-module')));
        $this->assertTrue($handler->shouldReport(new RuntimeException('Unexpected runtime failure')));
    }
}
