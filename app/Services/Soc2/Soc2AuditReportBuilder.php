<?php

namespace App\Services\Soc2;

use App\Enums\VirtualwareProvider;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Virtualware;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Soc2AuditReportBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Organization $organization): array
    {
        $generatedAt = now('UTC');

        /** @var Collection<int, Hardware> $hardwareAssets */
        $hardwareAssets = $organization->hardwares()
            ->orderBy('name')
            ->get();

        /** @var Collection<int, Virtualware> $virtualwareAssets */
        $virtualwareAssets = $organization->virtualwares()
            ->with('cloudTenant')
            ->orderBy('name')
            ->get();

        $hardwareReports = $hardwareAssets
            ->map(fn (Hardware $hardware): array => $this->hardwareReport($hardware))
            ->values();

        $virtualwareReports = $virtualwareAssets
            ->map(fn (Virtualware $virtualware): array => $this->virtualwareReport($virtualware))
            ->values();

        /** @var Collection<int, array<string, mixed>> $deviceReports */
        $deviceReports = $hardwareReports->concat($virtualwareReports)->values();

        $controls = [
            $this->controlEncryption($deviceReports),
            $this->controlAntivirus($deviceReports),
            $this->controlPatching($deviceReports),
            $this->controlAccessProviders($deviceReports),
            $this->controlInventoryFreshness($deviceReports, $generatedAt),
            $this->controlSbomCoverage($deviceReports),
        ];

        $statusCounts = [
            'pass' => 0,
            'partial' => 0,
            'fail' => 0,
            'insufficient_data' => 0,
        ];

        foreach ($controls as $control) {
            $statusCounts[$control['status']]++;
        }

        $overallStatus = match (true) {
            $statusCounts['fail'] > 0 => 'fail',
            $statusCounts['partial'] > 0 || $statusCounts['insufficient_data'] > 0 => 'partial',
            default => 'pass',
        };

        return [
            'schemaVersion' => '1.1',
            'reportType' => 'soc2_inventory_controls',
            'generatedAtUtc' => $generatedAt->toIso8601String(),
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'summary' => [
                'overallStatus' => $overallStatus,
                'deviceCount' => $deviceReports->count(),
                'hardwareCount' => $hardwareReports->count(),
                'virtualwareCount' => $virtualwareReports->count(),
                'devicesWithInventory' => $deviceReports->whereNotNull('inventoryCollectedAtUtc')->count(),
                'controlsPassed' => $statusCounts['pass'],
                'controlsPartial' => $statusCounts['partial'],
                'controlsFailed' => $statusCounts['fail'],
                'controlsInsufficientData' => $statusCounts['insufficient_data'],
            ],
            'controls' => $controls,
            'hardware' => $hardwareReports->all(),
            'virtualware' => $this->groupVirtualware($virtualwareReports),
            'devices' => $deviceReports->all(),
            'disclaimer' => 'This report summarizes inventory-derived control evidence for SOC 2 Trust Services Criteria. It is not a formal SOC 2 attestation.',
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    public function pdfLines(array $report): array
    {
        $lines = [
            'AssetBee SOC 2 Inventory Controls Report',
            'Generated: '.($report['generatedAtUtc'] ?? ''),
            'Organization: '.data_get($report, 'organization.name').' ('.data_get($report, 'organization.slug').')',
            '',
            'Summary',
            'Overall status: '.strtoupper((string) data_get($report, 'summary.overallStatus')),
            'Devices: '.data_get($report, 'summary.deviceCount').' total / '.data_get($report, 'summary.devicesWithInventory').' with inventory',
            'Hardware: '.data_get($report, 'summary.hardwareCount').' | Virtualware: '.data_get($report, 'summary.virtualwareCount'),
            'Controls: '.data_get($report, 'summary.controlsPassed').' pass / '.data_get($report, 'summary.controlsPartial').' partial / '.data_get($report, 'summary.controlsFailed').' fail / '.data_get($report, 'summary.controlsInsufficientData').' insufficient data',
            '',
            'Controls',
        ];

        foreach ($report['controls'] as $control) {
            $lines[] = '';
            $lines[] = ($control['id'] ?? '').' - '.($control['title'] ?? '');
            $lines[] = 'Status: '.strtoupper((string) ($control['status'] ?? ''));
            $lines[] = (string) ($control['description'] ?? '');

            foreach ($control['findings'] ?? [] as $finding) {
                $lines[] = '- '.$finding;
            }
        }

        $lines[] = '';
        $lines[] = 'Hardware';

        foreach ($report['hardware'] ?? [] as $device) {
            $lines = [...$lines, ...$this->pdfDeviceLines($device)];
        }

        if (($report['hardware'] ?? []) === []) {
            $lines[] = '';
            $lines[] = 'No hardware assets.';
        }

        $lines[] = '';
        $lines[] = 'Virtualware';

        $tenantGroups = data_get($report, 'virtualware.byCloudTenant', []);
        $ungrouped = data_get($report, 'virtualware.ungrouped', []);

        if ($tenantGroups === [] && $ungrouped === []) {
            $lines[] = '';
            $lines[] = 'No virtualware assets.';
        }

        foreach ($tenantGroups as $group) {
            $lines[] = '';
            $lines[] = 'Cloud tenant external ID: '.($group['externalId'] ?? '—')
                .' ('.strtoupper((string) ($group['provider'] ?? 'cloud'))
                .(filled($group['cloudTenantName'] ?? null) ? ' / '.$group['cloudTenantName'] : '')
                .')';

            foreach ($group['devices'] ?? [] as $device) {
                $lines = [...$lines, ...$this->pdfDeviceLines($device)];
            }
        }

        if ($ungrouped !== []) {
            $lines[] = '';
            $lines[] = 'Other virtualware';

            foreach ($ungrouped as $device) {
                $lines = [...$lines, ...$this->pdfDeviceLines($device)];
            }
        }

        $lines[] = '';
        $lines[] = (string) ($report['disclaimer'] ?? '');

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $device
     * @return list<string>
     */
    private function pdfDeviceLines(array $device): array
    {
        $lines = [
            '',
            ($device['name'] ?? 'Unknown').' ['.($device['serialNumber'] ?? 'no-serial').']',
        ];

        if (($device['assetType'] ?? null) === 'virtualware') {
            $lines[] = 'Type: '.($device['type'] ?? '—')
                .' | Provider: '.($device['provider'] ?? '—')
                .' | Inventory: '.($device['inventoryCollectedAtUtc'] ?? 'none');
        } else {
            $lines[] = 'Category: '.($device['category'] ?? '—')
                .' | Inventory: '.($device['inventoryCollectedAtUtc'] ?? 'none');
        }

        $lines[] = 'Encryption: '.data_get($device, 'encryption.status', 'unknown')
            .' | Antivirus enabled: '.(data_get($device, 'antivirus.enabledCount', 0))
            .' | Available updates: '.(data_get($device, 'updates.availableCount', 0))
            .' | SBOM components: '.(data_get($device, 'sbom.componentCount', 0));

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function hardwareReport(Hardware $hardware): array
    {
        $payload = is_array($hardware->inventory_payload) ? $hardware->inventory_payload : [];

        return [
            'id' => $hardware->id,
            'assetType' => 'hardware',
            'name' => $hardware->name,
            'serialNumber' => $hardware->serial_number,
            'category' => $hardware->category->value,
            'status' => $hardware->status->value,
            'operatingSystem' => $hardware->operating_system?->value,
            'inventoryCollectedAtUtc' => $hardware->inventory_collected_at?->toIso8601String(),
            ...$this->inventoryEvidence(
                $payload,
                bitlockerStatus: $hardware->bitlocker_status?->value,
                recoveryKeyStored: filled($hardware->bitlocker_recovery_key),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function virtualwareReport(Virtualware $virtualware): array
    {
        $payload = is_array($virtualware->inventory_payload) ? $virtualware->inventory_payload : [];

        return [
            'id' => $virtualware->id,
            'assetType' => 'virtualware',
            'name' => $virtualware->name,
            'serialNumber' => $virtualware->serial_number,
            'type' => $virtualware->category->value,
            'provider' => $virtualware->provider->value,
            'status' => $virtualware->status->value,
            'cloudTenantId' => $virtualware->cloud_tenant_id,
            'cloudTenantName' => $virtualware->cloudTenant?->name,
            'cloudTenantExternalId' => $virtualware->cloudTenant?->external_id,
            'inventoryCollectedAtUtc' => $virtualware->inventory_collected_at?->toIso8601String(),
            ...$this->inventoryEvidence($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function inventoryEvidence(
        array $payload,
        ?string $bitlockerStatus = null,
        bool $recoveryKeyStored = false,
    ): array {
        $encryptionVolumes = $this->probeList($payload, 'diskEncryption');
        $encryptedVolumes = collect($encryptionVolumes)->filter(
            fn (array $volume): bool => str_contains(strtolower((string) ($volume['state'] ?? '')), 'encrypted'),
        )->count();

        $antivirus = $this->probeList($payload, 'antivirus');
        $enabledAntivirus = collect($antivirus)->filter(fn (array $product): bool => ($product['enabled'] ?? null) === true);
        $upToDateAntivirus = $enabledAntivirus->filter(fn (array $product): bool => ($product['upToDate'] ?? null) !== false);

        $updates = $this->probeValue($payload, 'updates');
        $availableUpdates = is_array(data_get($updates, 'available')) ? data_get($updates, 'available') : [];
        $installedUpdates = is_array(data_get($updates, 'installed')) ? data_get($updates, 'installed') : [];

        $sbom = $this->probeValue($payload, 'sbom');
        $componentCount = 0;

        foreach (data_get($sbom, 'targets', []) as $target) {
            if (is_array($target) && is_array($target['components'] ?? null)) {
                $componentCount += count($target['components']);
            }
        }

        return [
            'encryption' => [
                'probeStatus' => data_get($payload, 'diskEncryption.status'),
                'status' => match (true) {
                    data_get($payload, 'diskEncryption.status') !== 'available' => 'unknown',
                    $encryptedVolumes > 0 => 'encrypted',
                    default => 'unencrypted',
                },
                'volumeCount' => count($encryptionVolumes),
                'encryptedVolumeCount' => $encryptedVolumes,
                'bitlockerStatus' => $bitlockerStatus,
                'recoveryKeyStored' => $recoveryKeyStored,
            ],
            'antivirus' => [
                'probeStatus' => data_get($payload, 'antivirus.status'),
                'productCount' => count($antivirus),
                'enabledCount' => $enabledAntivirus->count(),
                'upToDateEnabledCount' => $upToDateAntivirus->count(),
                'products' => collect($antivirus)->map(fn (array $product): array => [
                    'name' => $product['name'] ?? null,
                    'state' => $product['state'] ?? null,
                    'enabled' => $product['enabled'] ?? null,
                    'upToDate' => $product['upToDate'] ?? null,
                ])->values()->all(),
            ],
            'updates' => [
                'probeStatus' => data_get($payload, 'updates.status'),
                'installedCount' => count($installedUpdates),
                'availableCount' => count($availableUpdates),
            ],
            'loginProviders' => [
                'probeStatus' => data_get($payload, 'loginProviders.status'),
                'providers' => collect($this->probeList($payload, 'loginProviders'))->map(fn (array $provider): array => [
                    'name' => $provider['name'] ?? null,
                    'state' => $provider['state'] ?? null,
                ])->values()->all(),
            ],
            'domainWorkspace' => [
                'probeStatus' => data_get($payload, 'domainWorkspace.status'),
                'domain' => data_get($payload, 'domainWorkspace.value.domain'),
                'domainJoined' => data_get($payload, 'domainWorkspace.value.domainJoined'),
                'workspace' => data_get($payload, 'domainWorkspace.value.workspace'),
                'workspaceJoined' => data_get($payload, 'domainWorkspace.value.workspaceJoined'),
            ],
            'sbom' => [
                'probeStatus' => data_get($payload, 'sbom.status'),
                'format' => data_get($sbom, 'format'),
                'specVersion' => data_get($sbom, 'specVersion'),
                'generatedAtUtc' => data_get($sbom, 'generatedAtUtc'),
                'targetCount' => is_array(data_get($sbom, 'targets')) ? count(data_get($sbom, 'targets')) : 0,
                'componentCount' => $componentCount,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $virtualwareReports
     * @return array{byCloudTenant: list<array<string, mixed>>, ungrouped: list<array<string, mixed>>}
     */
    private function groupVirtualware(Collection $virtualwareReports): array
    {
        $byExternalId = [];
        $ungrouped = [];

        foreach ($virtualwareReports as $device) {
            $provider = VirtualwareProvider::tryFrom((string) ($device['provider'] ?? ''));
            $externalId = $device['cloudTenantExternalId'] ?? null;

            if ($provider?->isCloudProvider() && filled($externalId)) {
                $key = (string) $externalId;

                if (! isset($byExternalId[$key])) {
                    $byExternalId[$key] = [
                        'externalId' => $key,
                        'provider' => $provider->value,
                        'cloudTenantId' => $device['cloudTenantId'] ?? null,
                        'cloudTenantName' => $device['cloudTenantName'] ?? null,
                        'devices' => [],
                    ];
                }

                $byExternalId[$key]['devices'][] = $device;

                continue;
            }

            $ungrouped[] = $device;
        }

        ksort($byExternalId);

        return [
            'byCloudTenant' => array_values($byExternalId),
            'ungrouped' => $ungrouped,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlEncryption(Collection $devices): array
    {
        $assessed = $devices->filter(fn (array $device): bool => data_get($device, 'encryption.probeStatus') === 'available');
        $encrypted = $assessed->filter(fn (array $device): bool => data_get($device, 'encryption.status') === 'encrypted');

        $unencrypted = $assessed
            ->reject(fn (array $device): bool => data_get($device, 'encryption.status') === 'encrypted')
            ->map(fn (array $device): string => ($device['name'] ?? 'device').' is not fully encrypted.')
            ->take(10)
            ->values()
            ->all();

        return $this->control(
            id: 'CC6.6',
            title: 'Encryption of data at rest',
            description: 'Devices with collected disk encryption inventory should report encrypted volumes.',
            assessed: $assessed->count(),
            passing: $encrypted->count(),
            total: $devices->count(),
            findings: [
                $assessed->count().' devices have encryption inventory.',
                $encrypted->count().' of those report encrypted volumes.',
                ...$unencrypted,
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlAntivirus(Collection $devices): array
    {
        $assessed = $devices->filter(fn (array $device): bool => data_get($device, 'antivirus.probeStatus') === 'available');
        $protected = $assessed->filter(
            fn (array $device): bool => data_get($device, 'antivirus.enabledCount', 0) > 0
                && data_get($device, 'antivirus.upToDateEnabledCount', 0) > 0,
        );

        return $this->control(
            id: 'CC7.1',
            title: 'Malware protection',
            description: 'Devices should report at least one enabled and up-to-date antivirus product.',
            assessed: $assessed->count(),
            passing: $protected->count(),
            total: $devices->count(),
            findings: [
                $assessed->count().' devices have antivirus inventory.',
                $protected->count().' report an enabled, up-to-date product.',
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlPatching(Collection $devices): array
    {
        $assessed = $devices->filter(fn (array $device): bool => data_get($device, 'updates.probeStatus') === 'available');
        $current = $assessed->filter(fn (array $device): bool => data_get($device, 'updates.availableCount', 0) === 0);

        return $this->control(
            id: 'CC7.2',
            title: 'System patching',
            description: 'Devices with update inventory should have no outstanding available updates.',
            assessed: $assessed->count(),
            passing: $current->count(),
            total: $devices->count(),
            findings: [
                $assessed->count().' devices have update inventory.',
                $current->count().' have zero available updates.',
                'Outstanding available updates across fleet: '.$assessed->sum(fn (array $device): int => (int) data_get($device, 'updates.availableCount', 0)).'.',
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlAccessProviders(Collection $devices): array
    {
        $assessed = $devices->filter(fn (array $device): bool => data_get($device, 'loginProviders.probeStatus') === 'available');
        $configured = $assessed->filter(fn (array $device): bool => count(data_get($device, 'loginProviders.providers', [])) > 0);

        return $this->control(
            id: 'CC6.1',
            title: 'Logical access mechanisms',
            description: 'Devices should report configured login providers used for authentication.',
            assessed: $assessed->count(),
            passing: $configured->count(),
            total: $devices->count(),
            findings: [
                $assessed->count().' devices have login provider inventory.',
                $configured->count().' report one or more providers.',
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlInventoryFreshness(Collection $devices, CarbonInterface $generatedAt): array
    {
        $withInventory = $devices->filter(fn (array $device): bool => filled($device['inventoryCollectedAtUtc'] ?? null));
        $fresh = $withInventory->filter(function (array $device) use ($generatedAt): bool {
            $collectedAt = Carbon::parse((string) $device['inventoryCollectedAtUtc']);

            return $collectedAt->greaterThanOrEqualTo($generatedAt->copy()->subDays(30));
        });

        return $this->control(
            id: 'CC7.1-INV',
            title: 'Endpoint inventory freshness',
            description: 'Managed devices should have inventory collected within the last 30 days.',
            assessed: $devices->count(),
            passing: $fresh->count(),
            total: $devices->count(),
            findings: [
                $withInventory->count().' devices have any inventory.',
                $fresh->count().' were collected within 30 days.',
            ],
            requireAssessed: false,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function controlSbomCoverage(Collection $devices): array
    {
        $assessed = $devices->filter(fn (array $device): bool => data_get($device, 'sbom.probeStatus') === 'available');
        $withComponents = $assessed->filter(fn (array $device): bool => data_get($device, 'sbom.componentCount', 0) > 0);

        return $this->control(
            id: 'CC8.1',
            title: 'Software bill of materials coverage',
            description: 'Devices should provide an SBOM so installed software can be inventoried and reviewed.',
            assessed: $assessed->count(),
            passing: $withComponents->count(),
            total: $devices->count(),
            findings: [
                $assessed->count().' devices have SBOM inventory.',
                $withComponents->count().' include one or more components.',
                'Total SBOM components across fleet: '.$devices->sum(fn (array $device): int => (int) data_get($device, 'sbom.componentCount', 0)).'.',
            ],
        );
    }

    /**
     * @param  list<string>  $findings
     * @return array<string, mixed>
     */
    private function control(
        string $id,
        string $title,
        string $description,
        int $assessed,
        int $passing,
        int $total,
        array $findings,
        bool $requireAssessed = true,
    ): array {
        $status = match (true) {
            $requireAssessed && $assessed === 0 => 'insufficient_data',
            $total === 0 => 'insufficient_data',
            $passing === $assessed && $assessed > 0 => 'pass',
            $passing === 0 => 'fail',
            default => 'partial',
        };

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'metrics' => [
                'totalDevices' => $total,
                'assessedDevices' => $assessed,
                'passingDevices' => $passing,
            ],
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function probeList(array $payload, string $key): array
    {
        if (data_get($payload, "{$key}.status") !== 'available') {
            return [];
        }

        $value = data_get($payload, "{$key}.value");

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function probeValue(array $payload, string $key): ?array
    {
        if (data_get($payload, "{$key}.status") !== 'available') {
            return null;
        }

        $value = data_get($payload, "{$key}.value");

        return is_array($value) ? $value : null;
    }
}
