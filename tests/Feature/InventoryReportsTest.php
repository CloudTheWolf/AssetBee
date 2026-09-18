<?php

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Enums\InventoryReport;
use App\Enums\OrganizationRole;
use App\Enums\VirtualwareCategory;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Virtualware;
use Livewire\Livewire;

test('organization members can view the reports index', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('Pending updates'))
        ->assertSee(__('Missing antivirus'))
        ->assertSee(__('Unencrypted disks'))
        ->assertSee(__('Laptops & desktops'))
        ->assertSee(__('Full device inventory (SOC 2)'))
        ->assertSee(__('Untracked devices'))
        ->assertSee(__('Untracked servers & virtualware'));
});

test('guests cannot view reports', function () {
    $this->get(route('reports.index'))
        ->assertRedirect(route('login'));
});

test('unknown reports return not found', function () {
    actingAsOrganizationMember();

    $this->get(route('reports.show', 'not-a-report'))
        ->assertNotFound();
});

test('the pending updates report lists devices with outstanding updates', function () {
    [, $organization] = actingAsOrganizationMember();

    $pending = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Needs Patching',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'updates' => [
                'status' => 'available',
                'value' => [
                    'available' => [
                        [
                            'id' => 'KB999',
                            'title' => 'Critical Patch KB999',
                            'category' => 'Security Updates',
                            'installedAtUtc' => null,
                            'kbArticle' => 'KB999',
                        ],
                    ],
                ],
            ],
        ]),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Fully Patched',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Other Org Laptop',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'updates' => [
                'status' => 'available',
                'value' => [
                    'available' => [
                        [
                            'id' => 'KB111',
                            'title' => 'Foreign Patch',
                            'category' => 'Security Updates',
                            'installedAtUtc' => null,
                            'kbArticle' => 'KB111',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->get(route('reports.show', InventoryReport::PendingUpdates->value))
        ->assertOk()
        ->assertSee(__('Pending updates'))
        ->assertSee('Needs Patching')
        ->assertSee('Critical Patch KB999')
        ->assertDontSee('Fully Patched')
        ->assertDontSee('Other Org Laptop');

    expect($pending->fresh()->name)->toBe('Needs Patching');
});

test('the missing antivirus report lists unprotected devices', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'No AV',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'antivirus' => [
                'status' => 'available',
                'value' => [
                    [
                        'name' => 'WatchGuard EPDR',
                        'state' => 'disabled',
                        'enabled' => false,
                        'upToDate' => false,
                        'detail' => 'off',
                    ],
                ],
            ],
        ]),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Protected Laptop',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $this->get(route('reports.show', InventoryReport::MissingAntivirus->value))
        ->assertOk()
        ->assertSee('No AV')
        ->assertSee(__('Antivirus is disabled.'))
        ->assertDontSee('Protected Laptop');
});

test('the missing antivirus report does not treat unknown linux freshness as out of date', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Linux EDR',
        'operating_system' => HardwareOperatingSystem::Linux,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'platform' => 'linux',
            'antivirus' => [
                'status' => 'available',
                'value' => [
                    [
                        'name' => 'CrowdStrike Falcon',
                        'state' => 'active',
                        'enabled' => true,
                        'upToDate' => null,
                        'detail' => 'falcon-sensor.service',
                    ],
                ],
            ],
        ]),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Stale Definitions',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'antivirus' => [
                'status' => 'available',
                'value' => [
                    [
                        'name' => 'WatchGuard EPDR',
                        'state' => 'enabled',
                        'enabled' => true,
                        'upToDate' => false,
                        'detail' => 'definitions expired',
                    ],
                ],
            ],
        ]),
    ]);

    $this->get(route('reports.show', InventoryReport::MissingAntivirus->value))
        ->assertOk()
        ->assertSee('Stale Definitions')
        ->assertSee(__('Antivirus is out of date.'))
        ->assertDontSee('Linux EDR');
});

test('the unencrypted disks report includes bitlocker disabled hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Open Disk',
        'bitlocker_status' => BitLockerStatus::Disabled,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'diskEncryption' => [
                'status' => 'available',
                'value' => [
                    [
                        'volume' => 'C:',
                        'technology' => 'BitLocker',
                        'state' => 'FullyDecrypted',
                        'recoveryKeys' => [],
                        'keyProtectors' => [],
                    ],
                ],
            ],
        ]),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Encrypted Laptop',
        'bitlocker_status' => BitLockerStatus::Enabled,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);

    Livewire::test('pages::reports.show', ['report' => InventoryReport::UnencryptedDisks->value])
        ->assertSee('Open Disk')
        ->assertDontSee('Encrypted Laptop');
});

test('the stale inventory report includes devices never collected', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Never Seen',
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Fresh Inventory',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $this->get(route('reports.show', InventoryReport::StaleInventory->value))
        ->assertOk()
        ->assertSee('Never Seen')
        ->assertDontSee('Fresh Inventory');
});

test('the missing recovery keys report lists encrypted hardware without a stored key', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'No Recovery Key',
        'operating_system' => HardwareOperatingSystem::Windows11,
        'bitlocker_status' => BitLockerStatus::Enabled,
        'bitlocker_recovery_key' => null,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Keyed Laptop',
        'operating_system' => HardwareOperatingSystem::Windows11,
        'bitlocker_status' => BitLockerStatus::Enabled,
        'bitlocker_recovery_key' => '016148-202037-546898-706926-484627-138688-405482-446105',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);

    $this->get(route('reports.show', InventoryReport::MissingRecoveryKeys->value))
        ->assertOk()
        ->assertSee('No Recovery Key')
        ->assertDontSee('Keyed Laptop');
});

test('the unassigned devices report includes available laptops and desktops only', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Spare Laptop',
        'category' => HardwareCategory::Laptop,
        'status' => HardwareStatus::Available,
        'assigned_userware_id' => null,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Spare Desktop',
        'category' => HardwareCategory::Desktop,
        'status' => HardwareStatus::Available,
        'assigned_userware_id' => null,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Spare Server',
        'category' => HardwareCategory::Server,
        'status' => HardwareStatus::Available,
        'assigned_userware_id' => null,
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Spare VM',
        'assigned_userware_id' => null,
    ]);

    $this->get(route('reports.show', InventoryReport::UnassignedDevices->value))
        ->assertOk()
        ->assertSee('Spare Laptop')
        ->assertSee('Spare Desktop')
        ->assertDontSee('Spare Server')
        ->assertDontSee('Spare VM');
});

test('the laptops and desktops report lists devices with drone link status', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Linked Laptop',
        'category' => HardwareCategory::Laptop,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Unlinked Desktop',
        'category' => HardwareCategory::Desktop,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Office Server',
        'category' => HardwareCategory::Server,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(['hardwareType' => ['status' => 'available', 'value' => 'server']]),
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Should Not Appear VM',
    ]);

    $this->get(route('reports.show', InventoryReport::LaptopsDesktopsDrone->value))
        ->assertOk()
        ->assertSee(__('Laptops & desktops'))
        ->assertSee('Linked Laptop')
        ->assertSee(__('Linked to Drone'))
        ->assertSee('Unlinked Desktop')
        ->assertSee(__('Not linked to Drone'))
        ->assertDontSee('Office Server')
        ->assertDontSee('Should Not Appear VM');
});

test('the full device soc2 report lists hardware and virtualware with device types', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Soc2 Laptop',
        'category' => HardwareCategory::Laptop,
        'status' => HardwareStatus::InUse,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Soc2 Server',
        'category' => HardwareCategory::Server,
        'status' => HardwareStatus::Available,
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Soc2 Virtual Machine',
        'category' => VirtualwareCategory::Vm,
    ]);
    Hardware::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Foreign Device',
        'category' => HardwareCategory::Laptop,
    ]);

    $this->get(route('reports.show', InventoryReport::FullDevicesSoc2->value))
        ->assertOk()
        ->assertSee(__('Full device inventory (SOC 2)'))
        ->assertSee('Soc2 Laptop')
        ->assertSee(__('Laptop'))
        ->assertSee('Soc2 Server')
        ->assertSee(__('Server'))
        ->assertSee('Soc2 Virtual Machine')
        ->assertSee(__('VM'))
        ->assertDontSee('Foreign Device');
});

test('the untracked devices report lists stale or never-collected laptops and desktops', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Manual Laptop',
        'category' => HardwareCategory::Laptop,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Stale Desktop',
        'category' => HardwareCategory::Desktop,
        'inventory_collected_at' => now()->subDays(45),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Fresh Laptop',
        'category' => HardwareCategory::Laptop,
        'inventory_collected_at' => now()->subDays(5),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Stale Server',
        'category' => HardwareCategory::Server,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);

    $this->get(route('reports.show', InventoryReport::UntrackedDevices->value))
        ->assertOk()
        ->assertSee(__('Untracked devices'))
        ->assertSee('Manual Laptop')
        ->assertSee(__('Never collected.'))
        ->assertSee('Stale Desktop')
        ->assertDontSee('Fresh Laptop')
        ->assertDontSee('Stale Server');
});

test('the untracked servers and virtualware report lists stale or never-collected servers and virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Manual Server',
        'category' => HardwareCategory::Server,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);
    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Stale VM',
        'category' => VirtualwareCategory::Vm,
        'inventory_collected_at' => now()->subDays(45),
        'inventory_payload' => inventoryPayload(['type' => 'virtualware']),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Fresh Server',
        'category' => HardwareCategory::Server,
        'inventory_collected_at' => now()->subDays(5),
        'inventory_payload' => inventoryPayload(['hardwareType' => ['status' => 'available', 'value' => 'server']]),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Stale Laptop',
        'category' => HardwareCategory::Laptop,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);

    $this->get(route('reports.show', InventoryReport::UntrackedServersAndVirtualware->value))
        ->assertOk()
        ->assertSee(__('Untracked servers & virtualware'))
        ->assertSee('Manual Server')
        ->assertSee(__('Never collected.'))
        ->assertSee('Stale VM')
        ->assertDontSee('Fresh Server')
        ->assertDontSee('Stale Laptop');
});

test('reports index shows matching counts', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Needs Patching',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'updates' => [
                'status' => 'available',
                'value' => [
                    'available' => [
                        [
                            'id' => 'KB999',
                            'title' => 'Critical Patch KB999',
                            'category' => 'Security Updates',
                            'installedAtUtc' => null,
                            'kbArticle' => 'KB999',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    Livewire::test('pages::reports.index')
        ->assertSee(__('Pending updates'))
        ->assertSee('1');
});

test('members can download a branded report pdf', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Needs Patching',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'updates' => [
                'status' => 'available',
                'value' => [
                    'available' => [
                        [
                            'id' => 'KB999',
                            'title' => 'Critical Patch KB999',
                            'category' => 'Security Updates',
                            'installedAtUtc' => null,
                            'kbArticle' => 'KB999',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->get(route('reports.pdf', InventoryReport::PendingUpdates->value))
        ->assertOk();

    $contents = $response->streamedContent();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('pending-updates-'.$organization->slug)
        ->and($contents)->toStartWith('%PDF-1.4')
        ->and($contents)->toContain('/DCTDecode')
        ->and($contents)->toContain('/Im1')
        ->and($contents)->toContain('Needs Patching')
        ->and($contents)->toContain('Critical Patch KB999')
        ->and($contents)->not->toContain('016148-202037');
});

test('the laptops and desktops pdf includes drone link breakdown counts', function () {
    [, $organization] = actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Linked Laptop',
        'category' => HardwareCategory::Laptop,
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload(),
    ]);
    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Unlinked Desktop',
        'category' => HardwareCategory::Desktop,
        'inventory_collected_at' => null,
        'inventory_payload' => null,
    ]);

    $contents = $this->get(route('reports.pdf', InventoryReport::LaptopsDesktopsDrone->value))
        ->assertOk()
        ->streamedContent();

    expect($contents)->toContain('Linked Laptop')
        ->and($contents)->toContain('Unlinked Desktop')
        ->and($contents)->toContain('Linked to Drone')
        ->and($contents)->toContain('Not linked to Drone');
});

test('report pdf downloads do not include other organization devices', function () {
    actingAsOrganizationMember();

    Hardware::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Foreign Patch Device',
        'inventory_collected_at' => now(),
        'inventory_payload' => inventoryPayload([
            'updates' => [
                'status' => 'available',
                'value' => [
                    'available' => [
                        [
                            'id' => 'KB111',
                            'title' => 'Foreign Patch',
                            'category' => 'Security Updates',
                            'installedAtUtc' => null,
                            'kbArticle' => 'KB111',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $contents = $this->get(route('reports.pdf', InventoryReport::PendingUpdates->value))
        ->assertOk()
        ->streamedContent();

    expect($contents)->not->toContain('Foreign Patch Device')
        ->and($contents)->toContain('No devices match this report.');
});

test('guests cannot download report pdfs', function () {
    $this->get(route('reports.pdf', InventoryReport::PendingUpdates->value))
        ->assertRedirect(route('login'));
});

test('unknown report pdfs return not found', function () {
    actingAsOrganizationMember();

    $this->get(route('reports.pdf', 'not-a-report'))
        ->assertNotFound();
});
