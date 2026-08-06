<?php

use App\Actions\Assets\ImportCloudVirtualMachines;
use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Data\DiscoveredCloudVirtualMachine;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Virtualware;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use Livewire\Livewire;
use RuntimeException;

beforeEach(function () {
    $this->fakeDiscoverer = new class implements DiscoversCloudVirtualMachines
    {
        /** @var list<DiscoveredCloudVirtualMachine> */
        public array $machines = [];

        public bool $shouldFail = false;

        public ?string $lastRegion = null;

        public function supports(CloudTenant $tenant): bool
        {
            return $tenant->provider->supportsVmImport() && $tenant->hasCredentials();
        }

        public function discover(CloudTenant $tenant, ?string $region = null): array
        {
            if ($this->shouldFail) {
                throw new RuntimeException('AWS denied access.');
            }

            $this->lastRegion = $region;

            return $this->machines;
        }
    };

    $this->app->instance(
        CloudVirtualMachineDiscoveryManager::class,
        new CloudVirtualMachineDiscoveryManager([$this->fakeDiscoverer]),
    );
});

test('owners can discover and import ec2 instances as virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0abc123',
            name: 'web-1',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            instanceType: 't3.medium',
            privateIp: '10.0.1.10',
            publicIp: null,
            availabilityZone: 'eu-west-1a',
            subnetId: 'subnet-1',
            vpcId: 'vpc-1',
            disks: [
                [
                    'device_name' => '/dev/xvda',
                    'volume_id' => 'vol-1',
                    'size_gb' => 30,
                    'volume_type' => 'gp3',
                    'encrypted' => true,
                    'delete_on_termination' => true,
                ],
            ],
            terminationProtection: false,
            notes: 'Imported from AWS EC2',
        ),
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0def456',
            name: 'db-1',
            status: VirtualwareStatus::Stopped,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
    ];

    $component = Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('discoverVirtualMachines')
        ->assertSet('discoveryError', null);

    expect($component->instance()->discoveredMachines)->toHaveCount(2);

    $component->set('selectedExternalIds', ['i-0abc123'])
        ->call('importVirtualMachines')
        ->assertHasNoErrors();

    $virtualware = Virtualware::query()->where('external_id', 'i-0abc123')->first();

    expect($virtualware)->not->toBeNull()
        ->and($virtualware->organization_id)->toBe($organization->id)
        ->and($virtualware->cloud_tenant_id)->toBe($tenant->id)
        ->and($virtualware->name)->toBe('web-1')
        ->and($virtualware->provider)->toBe(VirtualwareProvider::Aws)
        ->and($virtualware->category)->toBe(VirtualwareCategory::Vm)
        ->and($virtualware->status)->toBe(VirtualwareStatus::Running)
        ->and($virtualware->instance_type)->toBe('t3.medium')
        ->and($virtualware->private_ip)->toBe('10.0.1.10')
        ->and($virtualware->availability_zone)->toBe('eu-west-1a')
        ->and($virtualware->subnet_id)->toBe('subnet-1')
        ->and($virtualware->vpc_id)->toBe('vpc-1')
        ->and($virtualware->termination_protection)->toBeFalse()
        ->and($virtualware->disks)->toHaveCount(1)
        ->and($tenant->fresh()->credentials_verified_at)->not->toBeNull()
        ->and(Virtualware::query()->where('external_id', 'i-0def456')->exists())->toBeFalse();
});

test('reimporting an instance updates the existing virtualware record', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $existing = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'cloud_tenant_id' => $tenant->id,
        'provider' => VirtualwareProvider::Aws,
        'external_id' => 'i-0abc123',
        'name' => 'old-name',
        'status' => VirtualwareStatus::Stopped,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0abc123',
            name: 'web-1',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
    ];

    $result = app(ImportCloudVirtualMachines::class)->handle($tenant, ['i-0abc123']);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(1)
        ->and($existing->fresh()->name)->toBe('web-1')
        ->and($existing->fresh()->status)->toBe(VirtualwareStatus::Running);
});

test('discovery failures are shown on the tenant page', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->shouldFail = true;

    $component = Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('discoverVirtualMachines')
        ->assertSet('discoveryError', 'AWS denied access.');

    expect($component->instance()->discoveredMachines)->toBe([]);
});

test('import requires credentials', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('discoverVirtualMachines')
        ->assertSet('discoveryError', 'Add credentials before discovering virtual machines.');
});

test('owners can discover instances for a selected region', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials([
        'access_key_id' => 'AKIAEXAMPLEKEY1234',
        'secret_access_key' => 'secret-example-key',
        'region' => 'eu-west-1',
    ])->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0west2',
            name: 'oregon-web',
            status: VirtualwareStatus::Running,
            region: 'us-west-2',
            notes: 'Imported from AWS EC2',
        ),
    ];

    $component = Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->assertSet('discoveryRegion', 'eu-west-1')
        ->set('discoveryRegion', 'us-west-2')
        ->call('discoverVirtualMachines')
        ->assertSet('discoveryError', null)
        ->assertSet('selectedExternalIds', ['i-0west2']);

    expect($component->instance()->discoveredMachines)->toHaveCount(1)
        ->and($this->fakeDiscoverer->lastRegion)->toBe('us-west-2');
});

test('tenant page renders no uncompiled blade attribute directives', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->assertDontSee('@disabled', escape: false)
        ->assertDontSee('@if', escape: false)
        ->assertDontSee('@endif', escape: false)
        ->assertDontSee('@php', escape: false);
});

test('discovery preselects every instance and binds selection client side', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0abc123',
            name: 'web-1',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0def456',
            name: 'db-1',
            status: VirtualwareStatus::Stopped,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
    ];

    Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('discoverVirtualMachines')
        ->assertSet('selectedExternalIds', ['i-0abc123', 'i-0def456'])
        ->assertSee('wire:model.self="selectedExternalIds"', escape: false)
        ->assertDontSee('wire:model.live', escape: false)
        ->assertSee('x-model="selectAll"', escape: false)
        ->assertSee('allIds.slice()', escape: false)
        ->assertDontSee('@js(', escape: false);
});

test('discovered instances are not carried in component state beyond display fields', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0abc123',
            name: 'web-1',
            status: VirtualwareStatus::Running,
            region: 'eu-west-1',
            notes: 'Imported from AWS EC2',
        ),
    ];

    $component = Livewire::test('pages::assets.cloud-tenants.show', ['cloudTenant' => $tenant])
        ->call('discoverVirtualMachines');

    expect($component->instance()->discoveredMachines[0])->not->toHaveKey('notes');

    // The rendered snapshot carries every public property, so the discovered
    // payload must not appear there or it rides along on each request.
    $component->assertDontSee('discoveredMachines', escape: false);
});
