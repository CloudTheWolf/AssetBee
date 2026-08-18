<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ClientIpAddressResolver
{
    /**
     * @var list<string>
     *
     * @see https://www.cloudflare.com/ips/
     */
    private const CLOUDFLARE_IP_RANGES = [
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '108.162.192.0/18',
        '131.0.72.0/22',
        '141.101.64.0/18',
        '162.158.0.0/15',
        '172.64.0.0/13',
        '173.245.48.0/20',
        '188.114.96.0/20',
        '190.93.240.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public function resolve(Request $request): ?string
    {
        $clientIpAddress = $request->ip();

        if ($clientIpAddress === null || ! IpUtils::checkIp($clientIpAddress, self::CLOUDFLARE_IP_RANGES)) {
            return $clientIpAddress;
        }

        $cloudflareConnectingIpAddress = $request->header('CF-Connecting-IP');

        if (! is_string($cloudflareConnectingIpAddress)) {
            return $clientIpAddress;
        }

        $cloudflareConnectingIpAddress = trim($cloudflareConnectingIpAddress);

        return filter_var($cloudflareConnectingIpAddress, FILTER_VALIDATE_IP) !== false
            ? $cloudflareConnectingIpAddress
            : $clientIpAddress;
    }
}
