<?php

use App\Actions\Assets\ImportCloudVirtualMachines;
use App\Actions\Assets\UpdateVirtualware;
use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Data\DiscoveredCloudVirtualMachine;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Virtualware;
use App\Services\Cloud\AwsEc2DiscoveryService;
use App\Services\Cloud\CloudVirtualMachineDiscoveryManager;
use Livewire\Livewire;

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
            return $this->machines;
        }
    };

    $this->app->instance(
        CloudVirtualMachineDiscoveryManager::class,
        new CloudVirtualMachineDiscoveryManager([$this->fakeDiscoverer]),
    );
});

test('importing ec2 instances stores infrastructure fields', function () {
    [, $organization] = actingAsOrganizationMember();

    $tenant = CloudTenant::factory()->aws()->withCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    $this->fakeDiscoverer->machines = [
        new DiscoveredCloudVirtualMachine(
            externalId: 'i-0abc123',
            name: 'web-1',
            status: VirtualwareStatus::Running,
            region: 'us-west-2',
            instanceType: 't3.medium',
            privateIp: '10.0.1.15',
            publicIp: '54.1.2.3',
            availabilityZone: 'us-west-2a',
            subnetId: 'subnet-abc',
            vpcId: 'vpc-xyz',
            secondaryIps: [
                [
                    'private_ip' => '10.0.1.16',
                    'public_ip' => '54.1.2.4',
                    'network_interface_id' => 'eni-abc',
                ],
                [
                    'private_ip' => '10.0.1.17',
                    'public_ip' => null,
                    'network_interface_id' => 'eni-abc',
                ],
            ],
            disks: [
                [
                    'device_name' => '/dev/xvda',
                    'volume_id' => 'vol-111',
                    'size_gb' => 30,
                    'volume_type' => 'gp3',
                    'encrypted' => true,
                    'delete_on_termination' => true,
                ],
                [
                    'device_name' => '/dev/sdf',
                    'volume_id' => 'vol-222',
                    'size_gb' => 100,
                    'volume_type' => 'gp3',
                    'encrypted' => false,
                    'delete_on_termination' => false,
                ],
            ],
            terminationProtection: true,
            notes: 'Imported from AWS EC2',
        ),
    ];

    $result = app(ImportCloudVirtualMachines::class)->handle($tenant, ['i-0abc123'], 'us-west-2');
    $virtualware = $result['virtualwares']->first();

    expect($virtualware->instance_type)->toBe('t3.medium')
        ->and($virtualware->private_ip)->toBe('10.0.1.15')
        ->and($virtualware->public_ip)->toBe('54.1.2.3')
        ->and($virtualware->availability_zone)->toBe('us-west-2a')
        ->and($virtualware->subnet_id)->toBe('subnet-abc')
        ->and($virtualware->vpc_id)->toBe('vpc-xyz')
        ->and($virtualware->secondary_ips)->toBe([
            [
                'private_ip' => '10.0.1.16',
                'public_ip' => '54.1.2.4',
                'network_interface_id' => 'eni-abc',
            ],
            [
                'private_ip' => '10.0.1.17',
                'public_ip' => null,
                'network_interface_id' => 'eni-abc',
            ],
        ])
        ->and($virtualware->region)->toBe('us-west-2')
        ->and($virtualware->termination_protection)->toBeTrue()
        ->and($virtualware->disks)->toHaveCount(2)
        ->and($virtualware->totalDiskSizeGb())->toBe(130);
});

test('aws discovery maps secondary private addresses and their public associations', function () {
    $service = new class extends AwsEc2DiscoveryService
    {
        /** @return list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}> */
        public function secondaryIps(array $instance): array
        {
            return $this->mapSecondaryIps($instance);
        }
    };

    $secondaryIps = $service->secondaryIps([
        'NetworkInterfaces' => [
            [
                'NetworkInterfaceId' => 'eni-primary',
                'PrivateIpAddresses' => [
                    [
                        'Primary' => true,
                        'PrivateIpAddress' => '10.0.1.15',
                    ],
                    [
                        'Primary' => false,
                        'PrivateIpAddress' => '10.0.1.16',
                        'Association' => ['PublicIp' => '54.1.2.4'],
                    ],
                ],
            ],
            [
                'NetworkInterfaceId' => 'eni-secondary',
                'PrivateIpAddresses' => [
                    [
                        'Primary' => false,
                        'PrivateIpAddress' => '10.0.2.10',
                    ],
                ],
            ],
        ],
    ]);

    expect($secondaryIps)->toBe([
        [
            'private_ip' => '10.0.1.16',
            'public_ip' => '54.1.2.4',
            'network_interface_id' => 'eni-primary',
        ],
        [
            'private_ip' => '10.0.2.10',
            'public_ip' => null,
            'network_interface_id' => 'eni-secondary',
        ],
    ]);
});

test('owners can edit infrastructure fields on virtualware', function () {
    [, $organization] = actingAsOrganizationMember();

    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
        'instance_type' => 't3.small',
        'private_ip' => '10.0.0.10',
    ]);

    Livewire::test('pages::assets.virtualware.show', ['virtualware' => $virtualware])
        ->set('instance_type', 'm5.large')
        ->set('private_ip', '10.0.0.20')
        ->set('public_ip', '1.2.3.4')
        ->set('availability_zone', 'eu-west-1b')
        ->set('subnet_id', 'subnet-123')
        ->set('vpc_id', 'vpc-456')
        ->set('region', 'eu-west-1')
        ->set('termination_protection', '1')
        ->call('save')
        ->assertHasNoErrors();

    expect($virtualware->fresh())->toMatchArray([
        'instance_type' => 'm5.large',
        'private_ip' => '10.0.0.20',
        'public_ip' => '1.2.3.4',
        'availability_zone' => 'eu-west-1b',
        'subnet_id' => 'subnet-123',
        'vpc_id' => 'vpc-456',
        'region' => 'eu-west-1',
        'termination_protection' => true,
    ]);
});

test('update virtualware action accepts infrastructure attributes', function () {
    [, $organization] = actingAsOrganizationMember();

    $virtualware = Virtualware::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $updated = app(UpdateVirtualware::class)->handle($virtualware, [
        'name' => $virtualware->name,
        'provider' => $virtualware->provider->value,
        'category' => $virtualware->category->value,
        'status' => $virtualware->status->value,
        'instance_type' => 'c6i.xlarge',
        'vpc_id' => 'vpc-abc',
        'subnet_id' => 'subnet-def',
        'termination_protection' => false,
        'disks' => [
            [
                'device_name' => '/dev/sda1',
                'volume_id' => 'vol-aaa',
                'size_gb' => 50,
                'volume_type' => 'gp3',
                'encrypted' => true,
                'delete_on_termination' => true,
            ],
        ],
    ]);

    expect($updated->instance_type)->toBe('c6i.xlarge')
        ->and($updated->vpc_id)->toBe('vpc-abc')
        ->and($updated->subnet_id)->toBe('subnet-def')
        ->and($updated->termination_protection)->toBeFalse()
        ->and($updated->disks[0]['size_gb'])->toBe(50);
});
