<?php

use App\SharedFeatures\Clock\Http\DomainClockMiddleware;
use App\Support\Exceptions\ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Mmtech\Rbac\Http\Middleware\BindGatewayUserToAuth;
use Mmtech\Rbac\Http\Middleware\ResolveGatewayUserInfo;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(DomainClockMiddleware::class);
        $preserveSettingValue = static fn (Request $request): bool => $request->is('api/ib/v1/admin/settings/*') && $request->isMethod('PATCH');
        $middleware->trimStrings(except: [$preserveSettingValue]);
        $middleware->convertEmptyStringsToNull(except: [$preserveSettingValue]);
        $middleware->prependToPriorityList(ThrottleRequests::class, ResolveGatewayUserInfo::class);
        $middleware->prependToPriorityList(ThrottleRequests::class, BindGatewayUserToAuth::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([
            ApiException::class,
        ]);

        $exceptions->render(
            fn (ApiException $exception) => $exception->render(),
        );
    })->create();
