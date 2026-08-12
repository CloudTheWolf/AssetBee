<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\CreateVirtualware;
use App\Actions\Assets\UpdateHardware;
use App\Actions\Assets\UpdateVirtualware;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertInventoryRequest;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Virtualware;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UpsertInventoryController extends Controller
{
    public function __invoke(
        UpsertInventoryRequest $request,
        CreateHardware $createHardware,
        UpdateHardware $updateHardware,
        CreateVirtualware $createVirtualware,
        UpdateVirtualware $updateVirtualware,
    ): JsonResponse {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $payload = $request->validated();

        if (($payload['type'] ?? null) === 'virtualware') {
            return $this->upsertVirtualware(
                $organization,
                $payload,
                $createVirtualware,
                $updateVirtualware,
            );
        }

        return $this->upsertHardware(
            $organization,
            $payload,
            $createHardware,
            $updateHardware,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertHardware(
        Organization $organization,
        array $payload,
        CreateHardware $createHardware,
        UpdateHardware $updateHardware,
    ): JsonResponse {
        $serialNumber = trim((string) data_get($payload, 'serialNumber.value'));

        /** @var array{0: Hardware, 1: bool} $result */
        $result = DB::transaction(function () use (
            $organization,
            $payload,
            $serialNumber,
            $createHardware,
            $updateHardware,
        ): array {
            $hardware = $organization->hardwares()
                ->where('serial_number', $serialNumber)
                ->lockForUpdate()
                ->first();

            $attributes = $this->hardwareAttributes($payload, $hardware);

            if ($hardware === null) {
                return [$createHardware->handle($organization, $attributes), true];
            }

            return [$updateHardware->handle($hardware, $attributes), false];
        }, attempts: 3);

        [$hardware, $created] = $result;

        return response()->json([
            'data' => [
                'id' => $hardware->id,
                'type' => 'hardware',
                'name' => $hardware->name,
                'category' => $hardware->category->value,
                'serialNumber' => $hardware->serial_number,
                'manufacturer' => $hardware->manufacturer,
                'model' => $hardware->model,
                'operatingSystem' => $hardware->operating_system?->value,
                'cpu' => $hardware->cpu,
                'ramGb' => $hardware->ram_gb,
                'storageGb' => $hardware->storage_gb,
                'bitlockerStatus' => $hardware->bitlocker_status?->value,
                'collectedAtUtc' => $hardware->inventory_collected_at?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertVirtualware(
        Organization $organization,
        array $payload,
        CreateVirtualware $createVirtualware,
        UpdateVirtualware $updateVirtualware,
    ): JsonResponse {
        $serialNumber = trim((string) data_get($payload, 'serialNumber.value'));

        /** @var array{0: Virtualware, 1: bool} $result */
        $result = DB::transaction(function () use (
            $organization,
            $payload,
            $serialNumber,
            $createVirtualware,
            $updateVirtualware,
        ): array {
            $virtualware = $organization->virtualwares()
                ->where('serial_number', $serialNumber)
                ->lockForUpdate()
                ->first();

            $attributes = $this->virtualwareAttributes($payload, $virtualware);

            if ($virtualware === null) {
                return [$createVirtualware->handle($organization, $attributes), true];
            }

            return [$updateVirtualware->handle($virtualware, $attributes), false];
        }, attempts: 3);

        [$virtualware, $created] = $result;

        return response()->json([
            'data' => [
                'id' => $virtualware->id,
                'type' => 'virtualware',
                'name' => $virtualware->name,
                'category' => $virtualware->category->value,
                'serialNumber' => $virtualware->serial_number,
                'provider' => $virtualware->provider->value,
                'status' => $virtualware->status->value,
                'collectedAtUtc' => $virtualware->inventory_collected_at?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function hardwareAttributes(array $payload, ?Hardware $hardware): array
    {
        $attributes = [
            'name' => trim((string) data_get($payload, 'deviceName.value')),
            'serial_number' => trim((string) data_get($payload, 'serialNumber.value')),
            'asset_tag' => $hardware?->asset_tag,
            'manufacturer' => $hardware?->manufacturer,
            'model' => $hardware?->model,
            'category' => $this->category($payload, $hardware),
            'status' => $hardware === null ? HardwareStatus::Available : $hardware->status,
            'operating_system' => $this->operatingSystem($payload),
            'is_vm_host' => $hardware === null ? false : $hardware->is_vm_host,
            'purchased_at' => $hardware?->purchased_at,
            'notes' => $hardware?->notes,
            'inventory_collected_at' => $payload['collectedAtUtc'],
            'inventory_payload' => $payload,
            'cpu' => $hardware?->cpu,
            'ram_gb' => $hardware?->ram_gb,
            'storage_gb' => $hardware?->storage_gb,
            'bitlocker_status' => $hardware?->bitlocker_status,
            'bitlocker_recovery_key' => $hardware?->bitlocker_recovery_key,
        ];

        if ($this->wasCollected($payload, 'cpu')) {
            $attributes['cpu'] = data_get($payload, 'cpu.value.model');
        }

        foreach (['manufacturer', 'model'] as $attribute) {
            if ($this->wasCollected($payload, $attribute)) {
                $value = data_get($payload, "{$attribute}.value");
                $attributes[$attribute] = is_string($value) && trim($value) !== ''
                    ? trim($value)
                    : null;
            }
        }

        if ($this->wasCollected($payload, 'memory')) {
            $attributes['ram_gb'] = $this->bytesToGigabytes(
                data_get($payload, 'memory.value.totalBytes'),
            );
        }

        if ($this->wasCollected($payload, 'disks')) {
            $disks = data_get($payload, 'disks.value', []);
            $totalBytes = 0;

            if (is_array($disks)) {
                foreach ($disks as $disk) {
                    if (is_array($disk) && is_int($disk['totalBytes'] ?? null)) {
                        $totalBytes += $disk['totalBytes'];
                    }
                }
            }

            $attributes['storage_gb'] = $this->bytesToGigabytes($totalBytes);
        }

        if ($this->wasCollected($payload, 'diskEncryption')) {
            $encryption = data_get($payload, 'diskEncryption.value', []);
            $encryptedDisks = is_array($encryption) ? $encryption : [];
            $hasEncryptedVolume = false;

            foreach ($encryptedDisks as $disk) {
                $state = is_array($disk) ? ($disk['state'] ?? null) : null;

                if (
                    is_string($state)
                    && str_contains(strtolower($state), 'encrypted')
                ) {
                    $hasEncryptedVolume = true;
                    break;
                }
            }

            $attributes['bitlocker_status'] = $hasEncryptedVolume
                ? BitLockerStatus::Enabled
                : BitLockerStatus::Disabled;
            $attributes['bitlocker_recovery_key'] = $this->recoveryKeys($encryptedDisks);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function virtualwareAttributes(array $payload, ?Virtualware $virtualware): array
    {
        return [
            'name' => trim((string) data_get($payload, 'deviceName.value')),
            'provider' => $virtualware?->provider ?? VirtualwareProvider::Other,
            'external_id' => $virtualware?->external_id,
            'serial_number' => trim((string) data_get($payload, 'serialNumber.value')),
            'category' => $virtualware?->category ?? VirtualwareCategory::Vm,
            'status' => $virtualware === null ? VirtualwareStatus::Running : $virtualware->status,
            'host_hardware_id' => $virtualware?->host_hardware_id,
            'cloud_tenant_id' => $virtualware?->cloud_tenant_id,
            'assigned_userware_id' => $virtualware?->assigned_userware_id,
            'notes' => $virtualware?->notes,
            'region' => $virtualware?->region,
            'instance_type' => $virtualware?->instance_type,
            'private_ip' => $virtualware?->private_ip,
            'public_ip' => $virtualware?->public_ip,
            'availability_zone' => $virtualware?->availability_zone,
            'subnet_id' => $virtualware?->subnet_id,
            'vpc_id' => $virtualware?->vpc_id,
            'secondary_ips' => $virtualware?->secondary_ips,
            'disks' => $virtualware?->disks,
            'termination_protection' => $virtualware?->termination_protection,
            'inventory_collected_at' => $payload['collectedAtUtc'],
            'inventory_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function category(array $payload, ?Hardware $hardware): HardwareCategory
    {
        if ($this->wasCollected($payload, 'hardwareType')) {
            $hardwareType = data_get($payload, 'hardwareType.value');

            if (is_string($hardwareType)) {
                return HardwareCategory::from($hardwareType);
            }
        }

        return $hardware === null ? HardwareCategory::Desktop : $hardware->category;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function operatingSystem(array $payload): HardwareOperatingSystem
    {
        $platform = strtolower((string) $payload['platform']);

        if ($this->wasCollected($payload, 'operatingSystem')) {
            $description = strtolower(implode(' ', array_filter([
                data_get($payload, 'operatingSystem.value.name'),
                data_get($payload, 'operatingSystem.value.version'),
                data_get($payload, 'operatingSystem.value.displayVersion'),
            ], 'is_string')));

            if (str_contains($description, 'server 2025')) {
                return HardwareOperatingSystem::WindowsServer2025;
            }

            if (str_contains($description, 'server 2022')) {
                return HardwareOperatingSystem::WindowsServer2022;
            }

            if (str_contains($description, 'server 2019')) {
                return HardwareOperatingSystem::WindowsServer2019;
            }

            if (str_contains($description, 'windows 11')) {
                return HardwareOperatingSystem::Windows11;
            }

            if (str_contains($description, 'windows 10')) {
                return HardwareOperatingSystem::Windows10;
            }
        }

        return match (strtolower($platform)) {
            'windows' => HardwareOperatingSystem::Windows,
            'linux' => HardwareOperatingSystem::Linux,
            'macos' => HardwareOperatingSystem::Macos,
            default => HardwareOperatingSystem::Other,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function wasCollected(array $payload, string $key): bool
    {
        return data_get($payload, "{$key}.status") === 'available';
    }

    private function bytesToGigabytes(mixed $bytes): ?int
    {
        if (! is_int($bytes) || $bytes <= 0) {
            return null;
        }

        return max(1, (int) round($bytes / 1_073_741_824));
    }

    /**
     * @param  array<array-key, mixed>  $encryptedDisks
     */
    private function recoveryKeys(array $encryptedDisks): ?string
    {
        $keys = [];

        foreach ($encryptedDisks as $disk) {
            if (! is_array($disk)) {
                continue;
            }

            $volume = is_string($disk['volume'] ?? null) ? $disk['volume'] : 'Unknown volume';
            $recoveryKeys = $disk['recoveryKeys'] ?? [];

            if (is_array($recoveryKeys)) {
                foreach ($recoveryKeys as $recoveryKey) {
                    if (is_string($recoveryKey) && $recoveryKey !== '') {
                        $keys[] = "{$volume}: {$recoveryKey}";
                    }
                }
            }

            $keyProtectors = $disk['keyProtectors'] ?? [];

            if (is_array($keyProtectors)) {
                foreach ($keyProtectors as $protector) {
                    if (
                        is_array($protector)
                        && is_string($protector['recoveryKey'] ?? null)
                        && $protector['recoveryKey'] !== ''
                    ) {
                        $keys[] = "{$volume}: {$protector['recoveryKey']}";
                    }
                }
            }
        }

        $keys = array_values(array_unique($keys));

        return $keys === [] ? null : implode(PHP_EOL, $keys);
    }
}
