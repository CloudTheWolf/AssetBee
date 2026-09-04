<?php

use App\Actions\Assets\DiscoverProxmoxGuests;
use App\Actions\Assets\ImportProxmoxGuests;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;

function fakeProxmoxApi(string $node = 'pve1'): void
{
    Http::fake([
        'https://pve.example:8006/api2/json/nodes' => Http::response([
            'data' => [
                ['node' => $node, 'status' => 'online'],
            ],
        ]),
        "https://pve.example:8006/api2/json/nodes/{$node}/qemu" => Http::response([
            'data' => [
                [
                    'vmid' => 100,
                    'name' => 'web-1',
                    'status' => 'running',
                    'cpus' => 2,
                    'maxmem' => 4 * 1024 * 1024 * 1024,
                ],
                [
                    'vmid' => 101,
                    'name' => 'db-1',
                    'status' => 'stopped',
                    'cpus' => 4,
                    'maxmem' => 8 * 1024 * 1024 * 1024,
                ],
            ],
        ]),
        "https://pve.example:8006/api2/json/nodes/{$node}/lxc" => Http::response([
            'data' => [
                [
                    'vmid' => 200,
                    'name' => 'cache-1',
                    'status' => 'running',
                    'cpus' => 1,
                    'maxmem' => 1 * 1024 * 1024 * 1024,
                ],
            ],
        ]),
        "https://pve.example:8006/api2/json/nodes/{$node}/qemu/100/config" => Http::response([
            'data' => [
                'name' => 'web-1',
                'cores' => 2,
                'memory' => 4096,
                'scsi0' => 'local-lvm:vm-100-disk-0,size=32G',
                'ipconfig0' => 'ip=10.0.1.10/24,gw=10.0.1.1',
            ],
        ]),
        "https://pve.example:8006/api2/json/nodes/{$node}/qemu/101/config" => Http::response([
            'data' => [
                'name' => 'db-1',
                'cores' => 4,
                'memory' => 8192,
                'scsi0' => 'local-lvm:vm-101-disk-0,size=100G',
            ],
        ]),
        "https://pve.example:8006/api2/json/nodes/{$node}/lxc/200/config" => Http::response([
            'data' => [
                'hostname' => 'cache-1',
                'cores' => 1,
                'memory' => 1024,
                'rootfs' => 'local-lvm:vm-200-disk-0,size=8G',
                'net0' => 'name=eth0,bridge=vmbr0,ip=10.0.1.20/24',
            ],
        ]),
    ]);
}

test('owners can discover and import proxmox guests as virtualware on the host', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'pve1',
    ]);

    fakeProxmoxApi('pve1');

    $component = Livewire::test('pages::assets.hardware.show', ['hardware' => $hardware])
        ->call('discoverProxmoxGuests')
        ->assertSet('discoveryError', null);

    expect($component->instance()->discoveredGuests)->toHaveCount(3);

    $component->set('selectedExternalIds', ['qemu/100', 'lxc/200'])
        ->call('importProxmoxGuests')
        ->assertHasNoErrors();

    $vm = Virtualware::query()->where('external_id', 'qemu/100')->first();
    $ct = Virtualware::query()->where('external_id', 'lxc/200')->first();

    expect($vm)->not->toBeNull()
        ->and($vm->organization_id)->toBe($organization->id)
        ->and($vm->provider)->toBe(VirtualwareProvider::Proxmox)
        ->and($vm->host_hardware_id)->toBe($hardware->id)
        ->and($vm->cloud_tenant_id)->toBeNull()
        ->and($vm->category)->toBe(VirtualwareCategory::Vm)
        ->and($vm->status)->toBe(VirtualwareStatus::Running)
        ->and($vm->private_ip)->toBe('10.0.1.10')
        ->and($ct)->not->toBeNull()
        ->and($ct->category)->toBe(VirtualwareCategory::Container)
        ->and($ct->host_hardware_id)->toBe($hardware->id);
});

test('imported guests are assigned to the hardware matching the proxmox node', function () {
    [, $organization] = actingAsOrganizationMember();

    $connectionHost = Hardware::factory()->withProxmoxCredentials([
        'node' => null,
        'api_url' => 'https://pve.example:8006',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'cluster-vip',
    ]);

    $targetHost = Hardware::factory()->vmHost()->create([
        'organization_id' => $organization->id,
        'name' => 'pve1',
    ]);

    fakeProxmoxApi('pve1');

    $result = app(ImportProxmoxGuests::class)->handle($connectionHost->fresh(), ['qemu/100']);

    $virtualware = $result['virtualwares']->first();

    expect($virtualware->host_hardware_id)->toBe($targetHost->id)
        ->and($virtualware->host_hardware_id)->not->toBe($connectionHost->id);
});

test('reimporting a migrated guest updates the host hardware', function () {
    [, $organization] = actingAsOrganizationMember();

    $oldHost = Hardware::factory()->vmHost()->create([
        'organization_id' => $organization->id,
        'name' => 'old-node',
    ]);

    $newHost = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'pve1',
    ]);

    Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'provider' => VirtualwareProvider::Proxmox,
        'external_id' => 'qemu/100',
        'name' => 'web-1',
        'host_hardware_id' => $oldHost->id,
        'status' => VirtualwareStatus::Stopped,
    ]);

    fakeProxmoxApi('pve1');

    $result = app(ImportProxmoxGuests::class)->handle($newHost, ['qemu/100']);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(1);

    $virtualware = Virtualware::query()->where('external_id', 'qemu/100')->first();

    expect($virtualware->host_hardware_id)->toBe($newHost->id)
        ->and($virtualware->status)->toBe(VirtualwareStatus::Running)
        ->and($virtualware->cloud_tenant_id)->toBeNull();
});

test('importing updates an existing virtualware with the same name', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'pve1',
    ]);

    $existing = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'web-1',
        'provider' => VirtualwareProvider::Other,
        'external_id' => null,
        'host_hardware_id' => null,
        'status' => VirtualwareStatus::Stopped,
    ]);

    fakeProxmoxApi('pve1');

    $result = app(ImportProxmoxGuests::class)->handle($hardware, ['qemu/100']);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(1)
        ->and(Virtualware::query()->where('name', 'web-1')->count())->toBe(1);

    $existing->refresh();

    expect($existing->provider)->toBe(VirtualwareProvider::Proxmox)
        ->and($existing->external_id)->toBe('qemu/100')
        ->and($existing->host_hardware_id)->toBe($hardware->id)
        ->and($existing->status)->toBe(VirtualwareStatus::Running)
        ->and($existing->cloud_tenant_id)->toBeNull();
});

test('discover proxmox guests action rejects non vm hosts', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'is_vm_host' => false,
    ]);

    app(DiscoverProxmoxGuests::class)->handle($hardware);
})->throws(RuntimeException::class);

test('fqdn node credentials resolve to the short proxmox node name', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1.lab.local',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'cluster-vip',
    ]);

    fakeProxmoxApi('pve1');

    $guests = app(DiscoverProxmoxGuests::class)->handle($hardware);

    expect($guests)->not->toBeEmpty()
        ->and($guests[0]->node)->toBe('pve1');
});

test('http 595 explains unreachable proxmox nodes', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
    ]);

    Http::fake([
        'https://pve.example:8006/api2/json/nodes' => Http::response([
            'data' => [
                ['node' => 'pve1', 'status' => 'online'],
            ],
        ]),
        'https://pve.example:8006/api2/json/nodes/pve1/qemu' => Http::response('No route to host', 595),
    ]);

    expect(fn () => app(DiscoverProxmoxGuests::class)->handle($hardware))
        ->toThrow(RuntimeException::class, 'HTTP 595');
});
