<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Virtualware;

test('soc2 audit endpoints require a valid organization api key', function () {
    $this->getJson('/api/v1/audit/soc2')
        ->assertUnauthorized();

    $this->get('/api/v1/audit/soc2.pdf')
        ->assertUnauthorized();
});

test('soc2 audit json endpoint returns programmatic control evidence', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Auditor');

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'UKMICHAELH25',
        'serial_number' => 'PF5FH9HR',
        'category' => HardwareCategory::Laptop,
        'status' => HardwareStatus::Available,
        'operating_system' => HardwareOperatingSystem::Windows11,
        'bitlocker_status' => BitLockerStatus::Enabled,
        'bitlocker_recovery_key' => '016148-202037-546898-706926-484627-138688-405482-446105',
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $this->withToken($plainTextKey)
        ->getJson('/api/v1/audit/soc2')
        ->assertOk()
        ->assertJsonPath('data.reportType', 'soc2_inventory_controls')
        ->assertJsonPath('data.schemaVersion', '1.1')
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonPath('data.summary.deviceCount', 1)
        ->assertJsonPath('data.summary.hardwareCount', 1)
        ->assertJsonPath('data.summary.virtualwareCount', 0)
        ->assertJsonPath('data.hardware.0.serialNumber', 'PF5FH9HR')
        ->assertJsonPath('data.devices.0.serialNumber', 'PF5FH9HR')
        ->assertJsonPath('data.devices.0.sbom.componentCount', 1)
        ->assertJsonPath('data.devices.0.encryption.recoveryKeyStored', true)
        ->assertJsonMissingPath('data.devices.0.encryption.recoveryKey');
});

test('soc2 audit includes virtualware with type and groups cloud providers by tenant external id', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Auditor');

    $awsTenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'Prod AWS',
        'external_id' => '111122223333',
    ]);

    $azureTenant = CloudTenant::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Prod Azure',
        'provider' => 'azure',
        'external_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'aws-web-01',
        'provider' => VirtualwareProvider::Aws,
        'category' => VirtualwareCategory::Vm,
        'status' => VirtualwareStatus::Running,
        'cloud_tenant_id' => $awsTenant->id,
        'serial_number' => 'i-0abc123',
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
        ]),
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'aws-db-01',
        'provider' => VirtualwareProvider::Aws,
        'category' => VirtualwareCategory::Database,
        'status' => VirtualwareStatus::Running,
        'cloud_tenant_id' => $awsTenant->id,
        'inventory_collected_at' => now('UTC')->subDays(2),
        'inventory_payload' => inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
        ]),
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'azure-app-01',
        'provider' => VirtualwareProvider::Azure,
        'category' => VirtualwareCategory::Vm,
        'status' => VirtualwareStatus::Running,
        'cloud_tenant_id' => $azureTenant->id,
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
        ]),
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'onprem-vmware-01',
        'provider' => VirtualwareProvider::Vmware,
        'category' => VirtualwareCategory::Vm,
        'status' => VirtualwareStatus::Running,
        'cloud_tenant_id' => null,
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload([
            'type' => 'virtualware',
            'platform' => 'linux',
        ]),
    ]);

    $response = $this->withToken($plainTextKey)
        ->getJson('/api/v1/audit/soc2')
        ->assertOk()
        ->assertJsonPath('data.summary.deviceCount', 4)
        ->assertJsonPath('data.summary.hardwareCount', 0)
        ->assertJsonPath('data.summary.virtualwareCount', 4)
        ->assertJsonPath('data.virtualware.byCloudTenant.0.externalId', '111122223333')
        ->assertJsonPath('data.virtualware.byCloudTenant.0.provider', 'aws')
        ->assertJsonPath('data.virtualware.byCloudTenant.0.cloudTenantName', 'Prod AWS')
        ->assertJsonCount(2, 'data.virtualware.byCloudTenant.0.devices')
        ->assertJsonPath('data.virtualware.byCloudTenant.1.externalId', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')
        ->assertJsonPath('data.virtualware.byCloudTenant.1.provider', 'azure')
        ->assertJsonCount(1, 'data.virtualware.byCloudTenant.1.devices')
        ->assertJsonCount(1, 'data.virtualware.ungrouped')
        ->assertJsonPath('data.virtualware.ungrouped.0.name', 'onprem-vmware-01')
        ->assertJsonPath('data.virtualware.ungrouped.0.type', 'vm')
        ->assertJsonPath('data.virtualware.byCloudTenant.0.devices.0.assetType', 'virtualware');

    $awsDevices = collect($response->json('data.virtualware.byCloudTenant.0.devices'));

    expect($awsDevices->pluck('name')->sort()->values()->all())->toBe(['aws-db-01', 'aws-web-01'])
        ->and($awsDevices->firstWhere('name', 'aws-web-01')['type'])->toBe('vm')
        ->and($awsDevices->firstWhere('name', 'aws-db-01')['type'])->toBe('database')
        ->and($response->json('data.devices'))->toHaveCount(4);
});

test('soc2 audit pdf endpoint returns a downloadable pdf', function () {
    $organization = Organization::factory()->create(['slug' => 'acme-corp']);
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Auditor');

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'external_id' => '999988887777',
        'name' => 'Acme AWS',
    ]);

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload(),
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'aws-audit-vm',
        'provider' => VirtualwareProvider::Aws,
        'category' => VirtualwareCategory::Vm,
        'cloud_tenant_id' => $tenant->id,
        'inventory_collected_at' => now('UTC')->subDay(),
        'inventory_payload' => inventoryPayload(['type' => 'virtualware']),
    ]);

    $response = $this->withToken($plainTextKey)
        ->get('/api/v1/audit/soc2.pdf')
        ->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('soc2-audit-acme-corp')
        ->and($response->streamedContent())->toStartWith('%PDF-1.4');
});
