<?php

use App\Http\Middleware\CmsJsonMutations;
use App\Http\Middleware\CmsRequestGuard;
use App\Http\Middleware\CmsRole;
use App\Http\Middleware\CmsSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(static function (Request $request): ?string {
            return $request->is('api/*') ? null : route('login');
        });
        $middleware->prepend(CmsRequestGuard::class);
        $middleware->append(CmsSecurityHeaders::class);
        $middleware->trustHosts(at: ['^127\.0\.0\.1$', '^localhost$'], subdomains: false);
        $middleware->alias([
            'cms.json' => CmsJsonMutations::class,
            'cms.role' => CmsRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static function (Request $request, \Throwable $exception): bool {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
