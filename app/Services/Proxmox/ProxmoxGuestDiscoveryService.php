<?php

namespace App\Services\Proxmox;

use App\Data\DiscoveredProxmoxGuest;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use RuntimeException;

class ProxmoxGuestDiscoveryService
{
    /**
     * @return list<DiscoveredProxmoxGuest>
     */
    public function discover(Hardware $hardware): array
    {
        if (! $hardware->is_vm_host) {
            throw new RuntimeException(__('Only VM host hardware can sync with Proxmox.'));
        }

        if (! $hardware->hasProxmoxCredentials()) {
            throw new RuntimeException(__('Add Proxmox credentials before discovering guests.'));
        }

        $credentials = $hardware->proxmox_credentials ?? [];
        $client = $this->makeClient($credentials);
        $node = $this->resolveNode($client, $hardware, $credentials);

        $guests = [];

        foreach ($client->qemuGuests($node) as $guest) {
            $mapped = $this->mapGuest($client, $node, $guest, VirtualwareCategory::Vm, 'qemu');

            if ($mapped !== null) {
                $guests[] = $mapped;
            }
        }

        foreach ($client->lxcGuests($node) as $guest) {
            $mapped = $this->mapGuest($client, $node, $guest, VirtualwareCategory::Container, 'lxc');

            if ($mapped !== null) {
                $guests[] = $mapped;
            }
        }

        return $guests;
    }

    public function resolveConnectionNode(Hardware $hardware): string
    {
        if (! $hardware->hasProxmoxCredentials()) {
            throw new RuntimeException(__('Add Proxmox credentials before discovering guests.'));
        }

        $credentials = $hardware->proxmox_credentials ?? [];

        return $this->resolveNode($this->makeClient($credentials), $hardware, $credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeClient(array $credentials): ProxmoxApiClient
    {
        return new ProxmoxApiClient($credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function resolveNode(ProxmoxApiClient $client, Hardware $hardware, array $credentials): string
    {
        $configured = trim((string) ($credentials['node'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        $nodes = $client->nodes();
        $names = collect($nodes)
            ->map(fn (array $node): string => trim((string) ($node['node'] ?? '')))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            throw new RuntimeException(__('No Proxmox nodes were returned by the API.'));
        }

        $hardwareName = strtolower(trim($hardware->name));
        $match = $names->first(fn (string $name): bool => strtolower($name) === $hardwareName);

        if ($match !== null) {
            return $match;
        }

        if ($names->count() === 1) {
            return (string) $names->first();
        }

        throw new RuntimeException(__('Set the Proxmox node name on this host so guests can be discovered.'));
    }

    /**
     * @param  array<string, mixed>  $guest
     */
    protected function mapGuest(
        ProxmoxApiClient $client,
        string $node,
        array $guest,
        VirtualwareCategory $category,
        string $type,
    ): ?DiscoveredProxmoxGuest {
        $vmid = $guest['vmid'] ?? null;

        if ($vmid === null || $vmid === '') {
            return null;
        }

        $externalId = $type.'/'.$vmid;
        $status = $this->mapStatus((string) ($guest['status'] ?? 'stopped'));
        $listName = trim((string) ($guest['name'] ?? ''));

        $config = [];

        try {
            $config = $type === 'qemu'
                ? $client->qemuConfig($node, $vmid)
                : $client->lxcConfig($node, $vmid);
        } catch (RuntimeException) {
            $config = [];
        }

        $configName = trim((string) ($config['name'] ?? $config['hostname'] ?? ''));
        $name = $configName !== '' ? $configName : ($listName !== '' ? $listName : $externalId);

        $cores = $guest['cpus'] ?? $config['cores'] ?? $config['cpulimit'] ?? null;
        $memoryMb = $guest['maxmem'] ?? null;

        if ($memoryMb === null && isset($config['memory'])) {
            $memoryMb = ((int) $config['memory']) * 1024 * 1024;
        }

        $instanceType = $this->instanceTypeSummary($cores, $memoryMb);
        $privateIp = $this->extractPrivateIp($config);
        $disks = $this->extractDisks($config, $type);

        return new DiscoveredProxmoxGuest(
            externalId: $externalId,
            name: $name,
            node: $node,
            status: $status,
            category: $category,
            instanceType: $instanceType,
            privateIp: $privateIp,
            disks: $disks,
            notes: __('Imported from Proxmox (:node)', ['node' => $node]),
        );
    }

    protected function mapStatus(string $status): VirtualwareStatus
    {
        return match (strtolower($status)) {
            'running' => VirtualwareStatus::Running,
            default => VirtualwareStatus::Stopped,
        };
    }

    protected function instanceTypeSummary(mixed $cores, mixed $memoryBytes): ?string
    {
        $parts = [];

        if (is_numeric($cores) && (float) $cores > 0) {
            $parts[] = __(':count vCPU', ['count' => (int) $cores]);
        }

        if (is_numeric($memoryBytes) && (float) $memoryBytes > 0) {
            $gb = (int) round(((float) $memoryBytes) / 1024 / 1024 / 1024);

            if ($gb > 0) {
                $parts[] = __(':size GB RAM', ['size' => $gb]);
            }
        }

        return $parts === [] ? null : implode(' / ', $parts);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function extractPrivateIp(array $config): ?string
    {
        foreach ($config as $key => $value) {
            if (! str_starts_with($key, 'ipconfig') || ! is_string($value)) {
                continue;
            }

            if (preg_match('/ip=([^,\/\s]+)/i', $value, $matches) === 1) {
                return $matches[1];
            }
        }

        if (isset($config['net0']) && is_string($config['net0'])) {
            if (preg_match('/ip=([^,\/\s]+)/i', $config['net0'], $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{device_name: string, volume_id: string|null, size_gb: int|null, volume_type: string|null, encrypted: bool|null, delete_on_termination: bool|null}>
     */
    protected function extractDisks(array $config, string $type): array
    {
        $disks = [];

        foreach ($config as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $isDiskKey = $type === 'qemu'
                ? (bool) preg_match('/^(scsi|sata|virtio|ide)\d+$/', $key)
                : (bool) preg_match('/^(rootfs|mp\d+)$/', $key);

            if (! $isDiskKey || str_contains($value, 'media=cdrom')) {
                continue;
            }

            $sizeGb = null;

            if (preg_match('/size=(\d+)([KMGT])?/i', $value, $matches) === 1) {
                $amount = (int) $matches[1];
                $unit = strtoupper($matches[2] ?? 'G');
                $sizeGb = match ($unit) {
                    'T' => $amount * 1024,
                    'G' => $amount,
                    'M' => max(1, (int) ceil($amount / 1024)),
                    'K' => max(1, (int) ceil($amount / 1024 / 1024)),
                    default => $amount,
                };
            }

            $volumeId = null;

            if (preg_match('/^([^,:]+)/', $value, $matches) === 1) {
                $volumeId = $matches[1];
            }

            $disks[] = [
                'device_name' => $key,
                'volume_id' => $volumeId,
                'size_gb' => $sizeGb,
                'volume_type' => $type === 'lxc' ? 'lxc' : 'proxmox',
                'encrypted' => null,
                'delete_on_termination' => null,
            ];
        }

        return $disks;
    }
}
