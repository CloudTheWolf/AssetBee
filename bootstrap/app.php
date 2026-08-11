<?php

use App\Http\Middleware\AuthenticateOrganizationApiKey;
use App\Http\Middleware\EnsureOrganizationSelected;
use App\Http\Middleware\EnsureSystemUser;
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
        // Prefer process env so this works under `config:cache` and before the
        // config repository is available (e.g. early Artisan kernel resolve).
        $trustedProxies = $_ENV['TRUSTED_PROXIES']
            ?? $_SERVER['TRUSTED_PROXIES']
            ?? '*';

        $middleware->trustProxies(
            at: $trustedProxies === '*' || $trustedProxies === ''
                ? '*'
                : array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) $trustedProxies),
                ))),
            headers: Request::HEADER_X_FORWARDED_TRAEFIK,
        );

        $middleware->trustHosts();

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);

        $middleware->alias([
            'organization.api-key' => AuthenticateOrganizationApiKey::class,
            'organization' => EnsureOrganizationSelected::class,
            'system' => EnsureSystemUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
