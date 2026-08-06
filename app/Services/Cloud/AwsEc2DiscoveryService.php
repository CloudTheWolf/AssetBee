<?php

namespace App\Services\Cloud;

use App\Contracts\Cloud\DiscoversCloudVirtualMachines;
use App\Data\DiscoveredCloudVirtualMachine;
use App\Enums\CloudTenantProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;
use RuntimeException;
use Throwable;

class AwsEc2DiscoveryService implements DiscoversCloudVirtualMachines
{
    public function supports(CloudTenant $tenant): bool
    {
        return $tenant->provider === CloudTenantProvider::Aws
            && $tenant->hasCredentials();
    }

    /**
     * @return list<DiscoveredCloudVirtualMachine>
     */
    public function discover(CloudTenant $tenant, ?string $region = null): array
    {
        if (! $this->supports($tenant)) {
            throw new RuntimeException(__('This cloud tenant does not support EC2 discovery.'));
        }

        $credentials = $tenant->credentials ?? [];
        $region = $region ?: (string) ($credentials['region'] ?? 'us-east-1');

        try {
            $client = $this->makeClient($credentials, $region);
            $result = $client->describeInstances([
                'Filters' => [
                    [
                        'Name' => 'instance-state-name',
                        'Values' => ['pending', 'running', 'stopping', 'stopped', 'shutting-down'],
                    ],
                ],
            ]);
        } catch (AwsException $exception) {
            throw new RuntimeException(
                __('Unable to list EC2 instances: :message', [
                    'message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
                ]),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                __('Unable to list EC2 instances: :message', ['message' => $exception->getMessage()]),
                previous: $exception,
            );
        }

        $instances = [];

        foreach ($result->get('Reservations') ?? [] as $reservation) {
            foreach ($reservation['Instances'] ?? [] as $instance) {
                $instanceId = (string) ($instance['InstanceId'] ?? '');

                if ($instanceId === '') {
                    continue;
                }

                $instances[$instanceId] = $instance;
            }
        }

        if ($instances === []) {
            return [];
        }

        $volumes = $this->describeVolumes($client, $instances);
        $terminationProtection = $this->describeTerminationProtection($client, array_keys($instances));

        $discovered = [];

        foreach ($instances as $instanceId => $instance) {
            $disks = $this->mapDisks($instance, $volumes);
            $secondaryIps = $this->mapSecondaryIps($instance);

            $discovered[] = new DiscoveredCloudVirtualMachine(
                externalId: $instanceId,
                name: $this->resolveName($instance, $instanceId),
                status: $this->mapStatus((string) ($instance['State']['Name'] ?? 'stopped')),
                region: $region,
                instanceType: filled($instance['InstanceType'] ?? null) ? (string) $instance['InstanceType'] : null,
                privateIp: filled($instance['PrivateIpAddress'] ?? null) ? (string) $instance['PrivateIpAddress'] : null,
                publicIp: filled($instance['PublicIpAddress'] ?? null) ? (string) $instance['PublicIpAddress'] : null,
                availabilityZone: filled($instance['Placement']['AvailabilityZone'] ?? null)
                    ? (string) $instance['Placement']['AvailabilityZone']
                    : null,
                subnetId: filled($instance['SubnetId'] ?? null) ? (string) $instance['SubnetId'] : null,
                vpcId: filled($instance['VpcId'] ?? null) ? (string) $instance['VpcId'] : null,
                secondaryIps: $secondaryIps,
                disks: $disks,
                terminationProtection: $terminationProtection[$instanceId] ?? null,
                notes: $this->buildNotes(
                    $instance,
                    $region,
                    $secondaryIps,
                    $disks,
                    $terminationProtection[$instanceId] ?? null,
                ),
            );
        }

        return $discovered;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeClient(array $credentials, string $region): Ec2Client
    {
        $config = [
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => (string) ($credentials['access_key_id'] ?? ''),
                'secret' => (string) ($credentials['secret_access_key'] ?? ''),
            ],
        ];

        if (! empty($credentials['session_token'])) {
            $config['credentials']['token'] = (string) $credentials['session_token'];
        }

        return new Ec2Client($config);
    }

    /**
     * @param  array<string, array<string, mixed>>  $instances
     * @return array<string, array<string, mixed>>
     */
    protected function describeVolumes(Ec2Client $client, array $instances): array
    {
        $volumeIds = [];

        foreach ($instances as $instance) {
            foreach ($instance['BlockDeviceMappings'] ?? [] as $mapping) {
                $volumeId = $mapping['Ebs']['VolumeId'] ?? null;

                if (filled($volumeId)) {
                    $volumeIds[] = (string) $volumeId;
                }
            }
        }

        $volumeIds = array_values(array_unique($volumeIds));

        if ($volumeIds === []) {
            return [];
        }

        $volumes = [];

        try {
            foreach (array_chunk($volumeIds, 100) as $chunk) {
                $result = $client->describeVolumes(['VolumeIds' => $chunk]);

                foreach ($result->get('Volumes') ?? [] as $volume) {
                    $volumes[(string) $volume['VolumeId']] = $volume;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $volumes;
    }

    /**
     * @param  list<string>  $instanceIds
     * @return array<string, bool|null>
     */
    protected function describeTerminationProtection(Ec2Client $client, array $instanceIds): array
    {
        $protection = [];

        foreach ($instanceIds as $instanceId) {
            try {
                $result = $client->describeInstanceAttribute([
                    'InstanceId' => $instanceId,
                    'Attribute' => 'disableApiTermination',
                ]);

                $protection[$instanceId] = (bool) ($result->get('DisableApiTermination')['Value'] ?? false);
            } catch (Throwable) {
                $protection[$instanceId] = null;
            }
        }

        return $protection;
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  array<string, array<string, mixed>>  $volumes
     * @return list<array{device_name: string, volume_id: string|null, size_gb: int|null, volume_type: string|null, encrypted: bool|null, delete_on_termination: bool|null}>
     */
    protected function mapDisks(array $instance, array $volumes): array
    {
        $disks = [];

        foreach ($instance['BlockDeviceMappings'] ?? [] as $mapping) {
            $volumeId = filled($mapping['Ebs']['VolumeId'] ?? null)
                ? (string) $mapping['Ebs']['VolumeId']
                : null;
            $volume = $volumeId !== null ? ($volumes[$volumeId] ?? null) : null;

            $disks[] = [
                'device_name' => (string) ($mapping['DeviceName'] ?? 'unknown'),
                'volume_id' => $volumeId,
                'size_gb' => isset($volume['Size']) ? (int) $volume['Size'] : null,
                'volume_type' => filled($volume['VolumeType'] ?? null) ? (string) $volume['VolumeType'] : null,
                'encrypted' => array_key_exists('Encrypted', $volume ?? [])
                    ? (bool) $volume['Encrypted']
                    : null,
                'delete_on_termination' => array_key_exists('DeleteOnTermination', $mapping['Ebs'] ?? [])
                    ? (bool) $mapping['Ebs']['DeleteOnTermination']
                    : null,
            ];
        }

        return $disks;
    }

    /**
     * @param  array<string, mixed>  $instance
     * @return list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}>
     */
    protected function mapSecondaryIps(array $instance): array
    {
        $secondaryIps = [];

        foreach ($instance['NetworkInterfaces'] ?? [] as $networkInterface) {
            foreach ($networkInterface['PrivateIpAddresses'] ?? [] as $address) {
                if (($address['Primary'] ?? false) || ! filled($address['PrivateIpAddress'] ?? null)) {
                    continue;
                }

                $secondaryIps[] = [
                    'private_ip' => (string) $address['PrivateIpAddress'],
                    'public_ip' => filled($address['Association']['PublicIp'] ?? null)
                        ? (string) $address['Association']['PublicIp']
                        : null,
                    'network_interface_id' => filled($networkInterface['NetworkInterfaceId'] ?? null)
                        ? (string) $networkInterface['NetworkInterfaceId']
                        : null,
                ];
            }
        }

        return $secondaryIps;
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    protected function resolveName(array $instance, string $instanceId): string
    {
        foreach ($instance['Tags'] ?? [] as $tag) {
            if (($tag['Key'] ?? null) === 'Name' && filled($tag['Value'] ?? null)) {
                return (string) $tag['Value'];
            }
        }

        return $instanceId;
    }

    protected function mapStatus(string $state): VirtualwareStatus
    {
        return match ($state) {
            'pending', 'running' => VirtualwareStatus::Running,
            'shutting-down', 'terminated' => VirtualwareStatus::Decommissioned,
            default => VirtualwareStatus::Stopped,
        };
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}>  $secondaryIps
     * @param  list<array{device_name: string, volume_id: string|null, size_gb: int|null, volume_type: string|null, encrypted: bool|null, delete_on_termination: bool|null}>  $disks
     */
    protected function buildNotes(
        array $instance,
        string $region,
        array $secondaryIps,
        array $disks,
        ?bool $terminationProtection,
    ): string {
        $parts = [
            'Imported from AWS EC2',
            'Region: '.$region,
        ];

        if (! empty($instance['InstanceType'])) {
            $parts[] = 'Type: '.$instance['InstanceType'];
        }

        if (! empty($instance['Placement']['AvailabilityZone'])) {
            $parts[] = 'Zone: '.$instance['Placement']['AvailabilityZone'];
        }

        if (! empty($instance['VpcId'])) {
            $parts[] = 'VPC: '.$instance['VpcId'];
        }

        if (! empty($instance['SubnetId'])) {
            $parts[] = 'Subnet: '.$instance['SubnetId'];
        }

        if (! empty($instance['PrivateIpAddress'])) {
            $parts[] = 'Private IP: '.$instance['PrivateIpAddress'];
        }

        if (! empty($instance['PublicIpAddress'])) {
            $parts[] = 'Public IP: '.$instance['PublicIpAddress'];
        }

        if ($secondaryIps !== []) {
            $parts[] = 'Secondary IPs: '.collect($secondaryIps)
                ->map(function (array $address): string {
                    $summary = $address['private_ip'];

                    if ($address['public_ip'] !== null) {
                        $summary .= ' (public '.$address['public_ip'].')';
                    }

                    return $summary;
                })
                ->implode(', ');
        }

        if ($disks !== []) {
            $diskSummary = collect($disks)
                ->map(function (array $disk): string {
                    $size = $disk['size_gb'] !== null ? $disk['size_gb'].'GB' : '?GB';
                    $type = $disk['volume_type'] ?? 'disk';

                    return $disk['device_name'].' '.$size.' '.$type;
                })
                ->implode(', ');

            $parts[] = 'Disks: '.$diskSummary;
        }

        if ($terminationProtection !== null) {
            $parts[] = 'Termination protection: '.($terminationProtection ? 'enabled' : 'disabled');
        }

        return implode("\n", $parts);
    }
}
