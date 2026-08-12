<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareOperatingSystem;
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
        ->assertJsonPath('data.name', 'RENAMED-DEVICE');

    expect(Hardware::query()->count())->toBe(1);
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
