<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

uses(TestCase::class);

test('application configures the traefik forwarded header bitfield', function () {
    expect(config('app.trusted_proxies'))->toBe('*')
        ->and(Request::HEADER_X_FORWARDED_TRAEFIK)->toBe(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PREFIX
        );
});

test('application reads trusted proxies from the process environment', function () {
    $originalTrustedProxies = getenv('TRUSTED_PROXIES');

    try {
        putenv('TRUSTED_PROXIES=172.18.0.2');
        TrustProxies::flushState();
        $this->refreshApplication();

        $request = Request::create(
            'http://assetbee.example.com/login',
            'GET',
            server: [
                'REMOTE_ADDR' => '172.18.0.3',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            ],
        );

        $middleware = new TrustProxies(app());

        $middleware->handle($request, function (Request $untrusted) {
            expect($untrusted->getClientIp())->toBe('172.18.0.3');

            return response()->make('ok');
        });
    } finally {
        $originalTrustedProxies === false
            ? putenv('TRUSTED_PROXIES')
            : putenv("TRUSTED_PROXIES={$originalTrustedProxies}");

        TrustProxies::flushState();
        $this->refreshApplication();
    }
});

test('traefik forwarded headers make the request secure behind a trusted proxy', function () {
    TrustProxies::at('*');
    TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_TRAEFIK);

    $request = Request::create(
        'http://assetbee.example.com/login',
        'GET',
        server: [
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'assetbee.example.com',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_SERVER' => 'traefik',
        ],
    );

    $middleware = new TrustProxies(app());

    $middleware->handle($request, function (Request $trusted) {
        expect($trusted->secure())->toBeTrue()
            ->and($trusted->getClientIp())->toBe('203.0.113.10')
            ->and($trusted->getHost())->toBe('assetbee.example.com')
            ->and($trusted->getPort())->toBe(443)
            ->and($trusted->getScheme())->toBe('https');

        return response()->make('ok');
    });
});

test('symfony exposes a dedicated traefik forwarded headers constant', function () {
    expect(SymfonyRequest::HEADER_X_FORWARDED_TRAEFIK)->toBe(Request::HEADER_X_FORWARDED_TRAEFIK);
});
