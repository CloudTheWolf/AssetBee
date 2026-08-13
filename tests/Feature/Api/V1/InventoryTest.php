<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Virtualware;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('inventory endpoint requires a valid organization api key', function () {
    $this->postJson('/api/v1/inventory', inventoryPayload())
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A valid organization API key is required.');
});

test('inventory endpoint enforces the version 1 schema', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $payload = inventoryPayload([
        'deviceName' => ['status' => 0],
    ]);
    unset($payload['manufacturer']);
    $payload['unexpected'] = true;

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deviceName.status', 'manufacturer', 'unexpected']);
});

test('inventory endpoint creates encrypted organization-scoped hardware', function () {
    $organization = Organization::factory()->create();
    [$apiKey, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'UKMICHAELH25')
        ->assertJsonPath('data.category', 'laptop')
        ->assertJsonPath('data.serialNumber', 'PF5FH9HR')
        ->assertJsonPath('data.manufacturer', 'Lenovo')
        ->assertJsonPath('data.model', 'ThinkPad P1 Gen 7')
        ->assertJsonPath('data.operatingSystem', 'windows_11')
        ->assertJsonMissingPath('data.bitlockerRecoveryKey');

    $hardware = Hardware::query()->sole();

    expect($hardware->organization_id)->toBe($organization->id)
        ->and($hardware->category->value)->toBe('laptop')
        ->and($hardware->manufacturer)->toBe('Lenovo')
        ->and($hardware->model)->toBe('ThinkPad P1 Gen 7')
        ->and($hardware->operating_system)->toBe(HardwareOperatingSystem::Windows11)
        ->and($hardware->cpu)->toBe('Intel(R) Core(TM) Ultra 7 155H')
        ->and($hardware->ram_gb)->toBe(48)
        ->and($hardware->bitlocker_status)->toBe(BitLockerStatus::Enabled)
        ->and($hardware->bitlocker_recovery_key)->toContain('016148-202037')
        ->and($hardware->inventory_payload)->toMatchArray([
            'schemaVersion' => '1.0',
            'platform' => 'windows',
            'type' => 'hardware',
        ])
        ->and(data_get($hardware->inventory_payload, 'sbom.value.format'))->toBe('CycloneDX');

    $storedPayload = DB::table('hardwares')->value('inventory_payload');

    expect($storedPayload)->not->toContain('016148-202037')
        ->and($apiKey->fresh()?->last_used_at)->not->toBeNull();
});

test('inventory endpoint creates virtualware assets for virtualware payloads', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
            'hardwareType' => [
                'status' => 'unsupported',
                'value' => null,
                'detail' => 'Virtualization detected: qemu.',
            ],
            'sbom' => [
                'status' => 'available',
                'value' => [
                    'format' => 'CycloneDX',
                    'specVersion' => '1.6',
                    'generatedAtUtc' => '2026-08-12T19:06:49.2102776+00:00',
                    'targets' => [
                        [
                            'bomRef' => 'host',
                            'kind' => 'host',
                            'name' => 'c4',
                            'components' => [
                                [
                                    'name' => 'bash',
                                    'version' => '5.2.37-2',
                                    'type' => 'library',
                                    'purl' => 'pkg:deb/bash@5.2.37-2',
                                ],
                            ],
                        ],
                        [
                            'bomRef' => 'container:643b4718eccf',
                            'kind' => 'container',
                            'name' => 'assetbee-queue-1',
                            'image' => 'cloudthewolf/assetbee-site:main',
                            'containerId' => '643b4718eccf',
                            'components' => [
                                [
                                    'name' => 'curl',
                                    'version' => '7.88.1-10',
                                    'type' => 'library',
                                    'purl' => 'pkg:deb/curl@7.88.1-10',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.type', 'virtualware')
        ->assertJsonPath('data.name', 'UKMICHAELH25')
        ->assertJsonPath('data.category', 'vm')
        ->assertJsonPath('data.provider', 'other')
        ->assertJsonPath('data.serialNumber', 'PF5FH9HR');

    expect(Hardware::query()->count())->toBe(0);

    $virtualware = Virtualware::query()->sole();

    expect($virtualware->organization_id)->toBe($organization->id)
        ->and($virtualware->serial_number)->toBe('PF5FH9HR')
        ->and(data_get($virtualware->inventory_payload, 'type'))->toBe('virtualware')
        ->and(data_get($virtualware->inventory_payload, 'sbom.value.targets.1.image'))
        ->toBe('cloudthewolf/assetbee-site:main');
});

test('inventory endpoint accepts linux virtualware payloads with container sbom details', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $overlayPath = '/var/lib/docker/overlay2/06c761ab2ff924afe8adbe8953f35e7c377a9b5a44fa5477dd67a3f0e4424c53/merged';
    $execFailure = 'OCI runtime exec failed: exec failed: unable to start container process: exec: "apk": executable file not found in $PATH: unknown';

    $payload = inventoryPayload([
        'type' => 'virtualware',
        'platform' => 'linux',
        'hardwareType' => [
            'status' => 'unsupported',
            'detail' => 'Asset type was configured as virtualware.',
        ],
        'cpu' => [
            'status' => 'available',
            'value' => [
                'model' => 'Intel(R) Xeon(R) Platinum 8259CL CPU @ 2.50GHz',
                'logicalProcessors' => 8,
            ],
        ],
        'disks' => [
            'status' => 'available',
            'value' => [
                [
                    'name' => '/',
                    'mountPoint' => '/',
                    'totalBytes' => 137135644672,
                    'availableBytes' => 52187070464,
                    'fileSystem' => 'ext4',
                ],
                [
                    'name' => $overlayPath,
                    'mountPoint' => $overlayPath,
                    'totalBytes' => 137135644672,
                    'availableBytes' => 52187070464,
                    'fileSystem' => 'overlay',
                ],
            ],
        ],
        'sbom' => [
            'status' => 'available',
            'value' => [
                'format' => 'CycloneDX',
                'specVersion' => '1.6',
                'generatedAtUtc' => '2026-08-13T07:50:04.5674951+00:00',
                'targets' => [
                    [
                        'bomRef' => 'host',
                        'kind' => 'host',
                        'name' => 'aws-wus-utl-iac1',
                        'components' => [
                            [
                                'name' => 'bash',
                                'version' => '5.2.15-2+b13',
                                'type' => 'library',
                                'purl' => 'pkg:deb/bash@5.2.15-2%2Bb13',
                            ],
                        ],
                    ],
                    [
                        'bomRef' => 'container:ae7277aefbcd',
                        'kind' => 'container',
                        'name' => 'flow-collector',
                        'image' => 'elastiflow/flow-collector:6.4.2',
                        'containerId' => 'ae7277aefbcd',
                        'components' => [],
                        'detail' => 'Container package inventory was unavailable from the package manager.',
                    ],
                    [
                        'bomRef' => 'container:4ab3266c2739',
                        'kind' => 'container',
                        'name' => 'coredns',
                        'image' => 'coredns/coredns:latest',
                        'containerId' => '4ab3266c2739',
                        'components' => [
                            [
                                'name' => $execFailure,
                                'type' => 'library',
                                'purl' => 'pkg:apk/'.rawurlencode($execFailure),
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    unset($payload['hardwareType']['value'], $payload['cpu']['value']['physicalCores']);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', $payload)
        ->assertCreated()
        ->assertJsonPath('data.type', 'virtualware')
        ->assertJsonPath('data.name', 'UKMICHAELH25');

    $virtualware = Virtualware::query()->sole();

    expect(data_get($virtualware->inventory_payload, 'sbom.value.targets.1.detail'))
        ->toBe('Container package inventory was unavailable from the package manager.')
        ->and(data_get($virtualware->inventory_payload, 'disks.value.1.mountPoint'))
        ->toBe($overlayPath);
});

test('hardware show page displays collected inventory details', function () {
    [, $organization] = actingAsOrganizationMember();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated();

    $hardware = Hardware::query()->sole();

    $this->get(route('assets.hardware.show', $hardware))
        ->assertOk()
        ->assertSee(__('Collected inventory'))
        ->assertSee('Lenovo')
        ->assertSee('ThinkPad P1 Gen 7')
        ->assertSee('WatchGuard EPDR')
        ->assertSee('CCPro Solutions')
        ->assertSee('2026-07 Cumulative Update')
        ->assertSee(__('Software bill of materials'))
        ->assertSee('CycloneDX')
        ->assertSee('8.1.0')
        ->assertSee('application')
        ->assertSee('pkg:generic/WatchGuard%20EPDR@8.1.0')
        ->assertSee(__('Search SBOM components…'))
        ->assertSee(__('Recovery key stored'));
});

test('hardware show page sbom list is searchable', function () {
    [, $organization] = actingAsOrganizationMember();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'sbom' => [
                'status' => 'available',
                'value' => [
                    'format' => 'CycloneDX',
                    'specVersion' => '1.6',
                    'generatedAtUtc' => '2026-08-11T14:44:20+00:00',
                    'targets' => [
                        [
                            'bomRef' => 'host',
                            'kind' => 'host',
                            'name' => 'UKMICHAELH25',
                            'components' => [
                                [
                                    'name' => 'WatchGuard EPDR',
                                    'version' => '8.1.0',
                                    'type' => 'application',
                                    'purl' => 'pkg:generic/WatchGuard%20EPDR@8.1.0',
                                    'publisher' => 'WatchGuard',
                                ],
                                [
                                    'name' => 'openssl',
                                    'version' => '3.0.2',
                                    'type' => 'library',
                                    'purl' => 'pkg:deb/debian/openssl@3.0.2',
                                    'publisher' => 'Debian',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertCreated();

    $hardware = Hardware::query()->sole();

    $component = Livewire::test('pages::assets.hardware.show', ['hardware' => $hardware])
        ->assertSee('WatchGuard EPDR')
        ->assertSee('openssl')
        ->set('sbomSearch', 'openssl');

    expect($component->instance()->filteredSbomTargets)
        ->toHaveCount(1)
        ->and($component->instance()->filteredSbomTargets[0]['matchingCount'])->toBe(1)
        ->and($component->instance()->filteredSbomTargets[0]['components'][0]['name'])->toBe('openssl');

    $component->set('sbomSearch', 'no-such-component')
        ->assertSee(__('No components match your search.'));

    expect($component->instance()->filteredSbomTargets)->toBeEmpty();
});

test('inventory endpoint updates existing virtualware by name without changing type', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'aws-wus-utl-iac1',
        'provider' => VirtualwareProvider::Aws,
        'category' => VirtualwareCategory::Vm,
        'status' => VirtualwareStatus::Running,
        'serial_number' => null,
        'instance_type' => 't3.medium',
        'private_ip' => '10.0.0.12',
    ]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'type' => 'virtualware',
            'deviceName' => ['status' => 'available', 'value' => 'AWS-WUS-UTL-IAC1'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.id', $virtualware->id)
        ->assertJsonPath('data.type', 'virtualware')
        ->assertJsonPath('data.provider', 'aws')
        ->assertJsonPath('data.name', 'aws-wus-utl-iac1')
        ->assertJsonPath('data.serialNumber', 'PF5FH9HR');

    expect(Virtualware::query()->count())->toBe(1)
        ->and(Hardware::query()->count())->toBe(0);

    $virtualware->refresh();

    expect($virtualware->provider)->toBe(VirtualwareProvider::Aws)
        ->and($virtualware->instance_type)->toBe('t3.medium')
        ->and($virtualware->private_ip)->toBe('10.0.0.12')
        ->and($virtualware->serial_number)->toBe('PF5FH9HR')
        ->and(data_get($virtualware->inventory_payload, 'type'))->toBe('virtualware');
});

test('inventory endpoint updates existing hardware by name without converting type', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'UKMICHAELH25',
        'category' => HardwareCategory::Laptop,
        'serial_number' => null,
        'cpu' => 'Existing CPU',
    ]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'type' => 'virtualware',
        ]))
        ->assertOk()
        ->assertJsonPath('data.id', $hardware->id)
        ->assertJsonPath('data.type', 'hardware')
        ->assertJsonPath('data.name', 'UKMICHAELH25');

    expect(Hardware::query()->count())->toBe(1)
        ->and(Virtualware::query()->count())->toBe(0)
        ->and($hardware->fresh()->cpu)->toBe('Existing CPU')
        ->and($hardware->fresh()->category)->toBe(HardwareCategory::Laptop)
        ->and($hardware->fresh()->serial_number)->toBe('PF5FH9HR');
});

test('virtualware show page displays searchable sbom inventory', function () {
    [, $organization] = actingAsOrganizationMember();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
            'hardwareType' => [
                'status' => 'unsupported',
                'value' => null,
                'detail' => 'Virtualization detected: qemu.',
            ],
            'sbom' => [
                'status' => 'available',
                'value' => [
                    'format' => 'CycloneDX',
                    'specVersion' => '1.6',
                    'generatedAtUtc' => '2026-08-12T19:06:49.2102776+00:00',
                    'targets' => [
                        [
                            'bomRef' => 'host',
                            'kind' => 'host',
                            'name' => 'c4',
                            'components' => [
                                [
                                    'name' => 'bash',
                                    'version' => '5.2.37-2',
                                    'type' => 'library',
                                    'purl' => 'pkg:deb/bash@5.2.37-2',
                                ],
                                [
                                    'name' => 'openssl',
                                    'version' => '3.0.2',
                                    'type' => 'library',
                                    'purl' => 'pkg:deb/openssl@3.0.2',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertCreated();

    $virtualware = Virtualware::query()->sole();

    $this->get(route('assets.virtualware.show', $virtualware))
        ->assertOk()
        ->assertSee(__('Collected inventory'))
        ->assertSee(__('Operating system'))
        ->assertSee('Microsoft Windows 11 Pro')
        ->assertSee('Intel(R) Core(TM) Ultra 7 155H')
        ->assertSee('WatchGuard EPDR')
        ->assertSee(__('Software bill of materials'))
        ->assertSee('CycloneDX')
        ->assertSee('bash')
        ->assertSee(__('Search SBOM components…'));

    $component = Livewire::test('pages::assets.virtualware.show', ['virtualware' => $virtualware])
        ->assertSee('bash')
        ->assertSee('openssl')
        ->set('sbomSearch', 'openssl');

    expect($component->instance()->filteredSbomTargets)
        ->toHaveCount(1)
        ->and($component->instance()->filteredSbomTargets[0]['matchingCount'])->toBe(1)
        ->and($component->instance()->filteredSbomTargets[0]['components'][0]['name'])->toBe('openssl');
});

test('inventory endpoint updates by serial number within the api key organization', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');

    $firstResponse = $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated();

    $this->withToken($plainTextKey)
        ->putJson('/api/v1/inventory', inventoryPayload([
            'collectedAtUtc' => '2026-08-06T11:50:48+00:00',
            'deviceName' => ['status' => 'available', 'value' => 'RENAMED-DEVICE'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.id', $firstResponse->json('data.id'))
        ->assertJsonPath('data.name', 'UKMICHAELH25');

    $hardware = Hardware::query()->sole();

    expect(Hardware::query()->count())->toBe(1)
        ->and($hardware->name)->toBe('UKMICHAELH25')
        ->and(data_get($hardware->inventory_payload, 'deviceName.value'))->toBe('RENAMED-DEVICE');
});

test('the same serial number remains isolated between organizations', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    [, $firstKey] = OrganizationApiKey::issue($firstOrganization, 'First collector');
    [, $secondKey] = OrganizationApiKey::issue($secondOrganization, 'Second collector');

    $this->withToken($firstKey)->postJson('/api/v1/inventory', inventoryPayload())->assertCreated();
    $this->withToken($secondKey)->postJson('/api/v1/inventory', inventoryPayload())->assertCreated();

    expect(Hardware::query()->count())->toBe(2);
});

test('revoked api keys cannot submit inventory', function () {
    $organization = Organization::factory()->create();
    [$apiKey, $plainTextKey] = OrganizationApiKey::issue($organization, 'Collector');
    $apiKey->update(['revoked_at' => now()]);

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertUnauthorized();
});
