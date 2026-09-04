<?php

namespace App\Services\Proxmox;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProxmoxApiClient
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        protected array $credentials,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->data('/nodes');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function qemuGuests(string $node): array
    {
        return $this->data('/nodes/'.rawurlencode($node).'/qemu');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lxcGuests(string $node): array
    {
        return $this->data('/nodes/'.rawurlencode($node).'/lxc');
    }

    /**
     * @return array<string, mixed>
     */
    public function qemuConfig(string $node, int|string $vmid): array
    {
        return $this->dataObject('/nodes/'.rawurlencode($node).'/qemu/'.rawurlencode((string) $vmid).'/config');
    }

    /**
     * @return array<string, mixed>
     */
    public function lxcConfig(string $node, int|string $vmid): array
    {
        return $this->dataObject('/nodes/'.rawurlencode($node).'/lxc/'.rawurlencode((string) $vmid).'/config');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function data(string $path): array
    {
        $payload = $this->get($path);
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values(array_filter($data, 'is_array'));

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataObject(string $path): array
    {
        $payload = $this->get($path);
        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        try {
            $response = $this->request()->get($this->url($path))->throw();
        } catch (RequestException $exception) {
            $errors = $exception->response->json('errors');

            throw new RuntimeException(
                __('Unable to reach Proxmox API: :message', [
                    'message' => $errors
                        ? json_encode($errors)
                        : $exception->getMessage(),
                ]),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                __('Unable to reach Proxmox API: :message', ['message' => $exception->getMessage()]),
                previous: $exception,
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    protected function request(): PendingRequest
    {
        $tokenId = (string) ($this->credentials['token_id'] ?? '');
        $tokenSecret = (string) ($this->credentials['token_secret'] ?? '');
        $verifyTls = array_key_exists('verify_tls', $this->credentials)
            ? (bool) $this->credentials['verify_tls']
            : true;

        return Http::acceptJson()
            ->timeout(30)
            ->withOptions(['verify' => $verifyTls])
            ->withHeaders([
                'Authorization' => 'PVEAPIToken='.$tokenId.'='.$tokenSecret,
            ]);
    }

    protected function url(string $path): string
    {
        $base = rtrim((string) ($this->credentials['api_url'] ?? ''), '/');

        if ($base === '') {
            throw new RuntimeException(__('Proxmox API URL is missing.'));
        }

        $path = '/'.ltrim($path, '/');

        return $base.'/api2/json'.$path;
    }
}
