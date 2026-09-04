<?php

use App\Actions\Assets\SyncAllProxmoxVirtualware;
use App\Actions\Assets\SyncImportedAwsEc2Instances;
use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Data\DiscoveredCloudVirtualMachine;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Virtualware;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

beforeEach(function () {
    $this->fakeDiscoverer = new class implements DiscoversCloudVirtualMachines
    {
        /** @var list<DiscoveredCloudVirtualMachine> */
        public array $machines = [];

        public function supports(CloudTenant $tenant): bool
        {
            return $tenant->provider->supportsVmImport() && $tenant->hasCredentials();
        }

        public function discover(CloudTenant $tenant, ?string $region = null): array
        {
            return array_values(array_filter(
                $this->machines,
                fn (DiscoveredCloudVirtualMachine $machine): bool => $machine->region === ($region ?? $machine->region),
            ));
        }
    };

    $this->app->instance(
        CloudVirtualMachineDiscoveryManager::class,
        new CloudVirtualMachineDiscoveryManager([$this->fakeDiscoverer]),
    );
});

test('virtualware sync is scheduled every fifteen minutes', function () {
    $events = collect(Schedule::events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'virtualware:sync'));

    expect($events)->not->toBeEmpty();

    $event = $events->first();

    expect($event->expression)->toBe('*/15 * * * *');
});

test('sync updates imported aws ec2 instances without creating new ones', function () {
    [, $organization] = actingAsOrganizationMember();
    $organization->forceFill(['virtualware_sync_enabled' => true])->save();

    $tenant = CloudTenant::factory()->aws()->withCredentials([
        'access_key_id' => 'AKIAEXAMPLEKEY1234',
        'secret_access_key' => 'secret',
        'region' => 'eu-west-1',
    ])->create([
        'organization_id' => $organization->id,
    ]);

    $existing = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'cloud_tenant_id' => $tenant->id,
        'provider' => VirtualwareProvider::Aws,
        'external_id' => 'i-existing',
        'name' => 'old-name',
        'region' => 'eu-west-1',
        'status' => VirtualwareStatus::Stopped,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-existing',
            name: 'updated-name',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-new',
            name: 'brand-new',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
    ];

    $result = app(SyncImportedAwsEc2Instances::class)->handle();

    expect($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(0);

    $existing->refresh();

    expect($existing->name)->toBe('updated-name')
        ->and($existing->status)->toBe(VirtualwareStatus::Running)
        ->and(Virtualware::query()->where('external_id', 'i-new')->exists())->toBeFalse();
});

test('sync pulls all proxmox guests creating new and updating existing', function () {
    [, $organization] = actingAsOrganizationMember();
    $organization->forceFill(['virtualware_sync_enabled' => true])->save();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
        'name' => 'pve1',
    ]);

    $existing = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'provider' => VirtualwareProvider::Proxmox,
        'external_id' => 'qemu/100',
        'name' => 'web-1-old',
        'host_hardware_id' => $hardware->id,
        'status' => VirtualwareStatus::Stopped,
    ]);

    Http::fake([
        'https://pve.example:8006/api2/json/nodes' => Http::response([
            'data' => [['node' => 'pve1', 'status' => 'online']],
        ]),
        'https://pve.example:8006/api2/json/nodes/pve1/qemu' => Http::response([
            'data' => [
                ['vmid' => 100, 'name' => 'web-1', 'status' => 'running', 'cpus' => 2, 'maxmem' => 4 * 1024 ** 3],
                ['vmid' => 102, 'name' => 'api-1', 'status' => 'running', 'cpus' => 1, 'maxmem' => 2 * 1024 ** 3],
            ],
        ]),
        'https://pve.example:8006/api2/json/nodes/pve1/lxc' => Http::response([
            'data' => [],
        ]),
        'https://pve.example:8006/api2/json/nodes/pve1/qemu/100/config' => Http::response([
            'data' => ['name' => 'web-1', 'cores' => 2, 'memory' => 4096],
        ]),
        'https://pve.example:8006/api2/json/nodes/pve1/qemu/102/config' => Http::response([
            'data' => ['name' => 'api-1', 'cores' => 1, 'memory' => 2048],
        ]),
    ]);

    $result = app(SyncAllProxmoxVirtualware::class)->handle();

    expect($result['created'])->toBe(1)
        ->and($result['updated'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $existing->refresh();

    expect($existing->name)->toBe('web-1')
        ->and($existing->status)->toBe(VirtualwareStatus::Running)
        ->and(Virtualware::query()->where('external_id', 'qemu/102')->exists())->toBeTrue();
});

test('sync skips organizations with virtualware sync disabled', function () {
    [, $organization] = actingAsOrganizationMember();
    expect($organization->virtualware_sync_enabled)->toBeFalse();

    CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    Hardware::factory()->withProxmoxCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-ignored',
            name: 'ignored',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
        ),
    ];

    Http::fake();

    $aws = app(SyncImportedAwsEc2Instances::class)->handle();
    $proxmox = app(SyncAllProxmoxVirtualware::class)->handle();

    expect($aws['tenants'])->toBe(0)
        ->and($proxmox['hosts'])->toBe(0);

    Http::assertNothingSent();
});

test('virtualware sync command runs aws and proxmox sync', function () {
    $this->mock(SyncImportedAwsEc2Instances::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn([
            'tenants' => 0,
            'updated' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => [],
        ]);
    });

    $this->mock(SyncAllProxmoxVirtualware::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn([
            'hosts' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ]);
    });

    expect(Artisan::call('virtualware:sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Virtualware sync completed.');
});

test('virtualware sync command succeeds when some hosts fail', function () {
    $this->mock(SyncImportedAwsEc2Instances::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn([
            'tenants' => 1,
            'updated' => 0,
            'created' => 0,
            'failed' => 1,
            'errors' => ['AWS tenant Example (#1): Access denied'],
        ]);
    });

    $this->mock(SyncAllProxmoxVirtualware::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn([
            'hosts' => 1,
            'created' => 2,
            'updated' => 1,
            'failed' => 0,
            'errors' => [],
        ]);
    });

    expect(Artisan::call('virtualware:sync'))->toBe(0)
        ->and(Artisan::output())->toContain('finished with some host or tenant failures');
});
