<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\CreateVirtualware;
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
        CreateVirtualware $createVirtualware,
    ): JsonResponse {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $payload = $request->validated();
        $serialNumber = trim((string) data_get($payload, 'serialNumber.value'));
        $deviceName = trim((string) data_get($payload, 'deviceName.value'));
        $preferredType = ($payload['type'] ?? null) === 'virtualware' ? 'virtualware' : 'hardware';

        /** @var array{0: Hardware|Virtualware, 1: bool} $result */
        $result = DB::transaction(function () use (
            $organization,
            $payload,
            $serialNumber,
            $deviceName,
            $preferredType,
            $createHardware,
            $createVirtualware,
        ): array {
            $existing = $this->findExistingAsset(
                $organization,
                $serialNumber,
                $deviceName,
                $preferredType,
            );

            if ($existing instanceof Hardware || $existing instanceof Virtualware) {
                $this->applyCollectedInventory($existing, $payload, $serialNumber);

                return [$existing->refresh(), false];
            }

            if ($preferredType === 'virtualware') {
                return [$createVirtualware->handle($organization, $this->virtualwareAttributes($payload)), true];
            }

            return [$createHardware->handle($organization, $this->hardwareAttributes($payload)), true];
        }, attempts: 3);

        [$asset, $created] = $result;

        return $this->responseFor($asset, $created);
    }

    private function findExistingAsset(
        Organization $organization,
        string $serialNumber,
        string $deviceName,
        string $preferredType,
    ): Hardware|Virtualware|null {
        if ($serialNumber !== '') {
            $bySerial = $this->preferType(
                $preferredType,
                $organization->hardwares()->where('serial_number', $serialNumber)->lockForUpdate()->first(),
                $organization->virtualwares()->where('serial_number', $serialNumber)->lockForUpdate()->first(),
            );

            if ($bySerial !== null) {
                return $bySerial;
            }
        }

        if ($deviceName === '') {
            return null;
        }

        $normalizedName = mb_strtolower($deviceName);

        return $this->preferType(
            $preferredType,
            $organization->hardwares()->whereRaw('LOWER(name) = ?', [$normalizedName])->lockForUpdate()->first(),
            $organization->virtualwares()->whereRaw('LOWER(name) = ?', [$normalizedName])->lockForUpdate()->first(),
        );
    }

    private function preferType(
        string $preferredType,
        ?Hardware $hardware,
        ?Virtualware $virtualware,
    ): Hardware|Virtualware|null {
        if ($preferredType === 'virtualware') {
            return $virtualware ?? $hardware;
        }

        return $hardware ?? $virtualware;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCollectedInventory(
        Hardware|Virtualware $asset,
        array $payload,
        string $serialNumber,
    ): void {
        $attributes = [
            'inventory_collected_at' => $payload['collectedAtUtc'],
            'inventory_payload' => $payload,
        ];

        if ($serialNumber !== '' && ! filled($asset->serial_number)) {
            $attributes['serial_number'] = $serialNumber;
        }

        $asset->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function hardwareAttributes(array $payload): array
    {
        $attributes = [
            'name' => trim((string) data_get($payload, 'deviceName.value')),
            'serial_number' => trim((string) data_get($payload, 'serialNumber.value')),
            'asset_tag' => null,
            'manufacturer' => null,
            'model' => null,
            'category' => $this->category($payload),
            'status' => HardwareStatus::Available,
            'operating_system' => $this->operatingSystem($payload),
            'is_vm_host' => false,
            'purchased_at' => null,
            'notes' => null,
            'inventory_collected_at' => $payload['collectedAtUtc'],
            'inventory_payload' => $payload,
            'cpu' => null,
            'ram_gb' => null,
            'storage_gb' => null,
            'bitlocker_status' => null,
            'bitlocker_recovery_key' => null,
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
    private function virtualwareAttributes(array $payload): array
    {
        return [
            'name' => trim((string) data_get($payload, 'deviceName.value')),
            'provider' => VirtualwareProvider::Other,
            'external_id' => null,
            'serial_number' => trim((string) data_get($payload, 'serialNumber.value')),
            'category' => VirtualwareCategory::Vm,
            'status' => VirtualwareStatus::Running,
            'host_hardware_id' => null,
            'cloud_tenant_id' => null,
            'assigned_userware_id' => null,
            'notes' => null,
            'region' => null,
            'instance_type' => null,
            'private_ip' => null,
            'public_ip' => null,
            'availability_zone' => null,
            'subnet_id' => null,
            'vpc_id' => null,
            'secondary_ips' => null,
            'disks' => null,
            'termination_protection' => null,
            'inventory_collected_at' => $payload['collectedAtUtc'],
            'inventory_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function category(array $payload): HardwareCategory
    {
        if ($this->wasCollected($payload, 'hardwareType')) {
            $hardwareType = data_get($payload, 'hardwareType.value');

            if (is_string($hardwareType)) {
                return HardwareCategory::from($hardwareType);
            }
        }

        return HardwareCategory::Desktop;
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

    private function responseFor(Hardware|Virtualware $asset, bool $created): JsonResponse
    {
        if ($asset instanceof Virtualware) {
            return response()->json([
                'data' => [
                    'id' => $asset->id,
                    'type' => 'virtualware',
                    'name' => $asset->name,
                    'category' => $asset->category->value,
                    'serialNumber' => $asset->serial_number,
                    'provider' => $asset->provider->value,
                    'status' => $asset->status->value,
                    'collectedAtUtc' => $asset->inventory_collected_at?->toIso8601String(),
                ],
            ], $created ? 201 : 200);
        }

        return response()->json([
            'data' => [
                'id' => $asset->id,
                'type' => 'hardware',
                'name' => $asset->name,
                'category' => $asset->category->value,
                'serialNumber' => $asset->serial_number,
                'manufacturer' => $asset->manufacturer,
                'model' => $asset->model,
                'operatingSystem' => $asset->operating_system?->value,
                'cpu' => $asset->cpu,
                'ramGb' => $asset->ram_gb,
                'storageGb' => $asset->storage_gb,
                'bitlockerStatus' => $asset->bitlocker_status?->value,
                'collectedAtUtc' => $asset->inventory_collected_at?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }
}
