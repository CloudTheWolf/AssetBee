<?php

namespace App\Data;

use App\Enums\VirtualwareStatus;

readonly class DiscoveredCloudVirtualMachine
{
    /**
     * @param  list<array{device_name: string, volume_id: string|null, size_gb: int|null, volume_type: string|null, encrypted: bool|null, delete_on_termination: bool|null}>  $disks
     * @param  list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}>  $secondaryIps
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public VirtualwareStatus $status,
        public string $region,
        public ?string $instanceType = null,
        public ?string $privateIp = null,
        public ?string $publicIp = null,
        public ?string $availabilityZone = null,
        public ?string $subnetId = null,
        public ?string $vpcId = null,
        public array $secondaryIps = [],
        public array $disks = [],
        public ?bool $terminationProtection = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array{
     *     external_id: string,
     *     name: string,
     *     status: string,
     *     region: string,
     *     instance_type: string|null,
     *     private_ip: string|null,
     *     public_ip: string|null,
     *     availability_zone: string|null,
     *     subnet_id: string|null,
     *     vpc_id: string|null,
     *     secondary_ips: list<array{private_ip: string, public_ip: string|null, network_interface_id: string|null}>,
     *     disks: list<array{device_name: string, volume_id: string|null, size_gb: int|null, volume_type: string|null, encrypted: bool|null, delete_on_termination: bool|null}>,
     *     termination_protection: bool|null,
     *     notes: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'status' => $this->status->value,
            'region' => $this->region,
            'instance_type' => $this->instanceType,
            'private_ip' => $this->privateIp,
            'public_ip' => $this->publicIp,
            'availability_zone' => $this->availabilityZone,
            'subnet_id' => $this->subnetId,
            'vpc_id' => $this->vpcId,
            'secondary_ips' => $this->secondaryIps,
            'disks' => $this->disks,
            'termination_protection' => $this->terminationProtection,
            'notes' => $this->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toVirtualwareAttributes(): array
    {
        return [
            'name' => $this->name,
            'external_id' => $this->externalId,
            'status' => $this->status,
            'region' => $this->region,
            'instance_type' => $this->instanceType,
            'private_ip' => $this->privateIp,
            'public_ip' => $this->publicIp,
            'availability_zone' => $this->availabilityZone,
            'subnet_id' => $this->subnetId,
            'vpc_id' => $this->vpcId,
            'secondary_ips' => $this->secondaryIps === [] ? null : $this->secondaryIps,
            'disks' => $this->disks === [] ? null : $this->disks,
            'termination_protection' => $this->terminationProtection,
            'notes' => $this->notes,
        ];
    }
}
