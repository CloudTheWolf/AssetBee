<?php

use App\Support\ClientIpAddressResolver;
use Illuminate\Http\Request;

test('it resolves the cloudflare visitor ip from a cloudflare request', function () {
    $request = Request::create(
        'https://assetbee.example.com',
        server: [
            'REMOTE_ADDR' => '172.68.205.102',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.25',
        ],
    );

    expect((new ClientIpAddressResolver)->resolve($request))->toBe('198.51.100.25');
});

test('it ignores a spoofed cloudflare header from other addresses', function () {
    $request = Request::create(
        'https://assetbee.example.com',
        server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.25',
        ],
    );

    expect((new ClientIpAddressResolver)->resolve($request))->toBe('203.0.113.10');
});

test('it ignores an invalid cloudflare visitor ip', function () {
    $request = Request::create(
        'https://assetbee.example.com',
        server: [
            'REMOTE_ADDR' => '172.68.205.102',
            'HTTP_CF_CONNECTING_IP' => 'not-an-ip-address',
        ],
    );

    expect((new ClientIpAddressResolver)->resolve($request))->toBe('172.68.205.102');
});
