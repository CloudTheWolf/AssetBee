<?php

namespace App\Data;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareStatus;

readonly class DiscoveredProxmoxGuest
{
    /**
     * @param  list<array{device_name?: string, volume_id?: string|null, size_gb?: int|null, volume_type?: string|null, encrypted?: bool|null, delete_on_termination?: bool|null}>  $disks
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $node,
        public VirtualwareStatus $status,
        public VirtualwareCategory $category,
        public ?string $instanceType = null,
        public ?string $privateIp = null,
        public array $disks = [],
        public ?string $notes = null,
    ) {}

    /**
     * @return array{
     *     external_id: string,
     *     name: string,
     *     node: string,
     *     status: string,
     *     category: string,
     *     instance_type: string|null,
     *     private_ip: string|null,
     *     disks: list<array{device_name?: string, volume_id?: string|null, size_gb?: int|null, volume_type?: string|null, encrypted?: bool|null, delete_on_termination?: bool|null}>,
     *     notes: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'node' => $this->node,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'instance_type' => $this->instanceType,
            'private_ip' => $this->privateIp,
            'disks' => $this->disks,
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
            'category' => $this->category,
            'instance_type' => $this->instanceType,
            'private_ip' => $this->privateIp,
            'disks' => $this->disks === [] ? null : $this->disks,
            'notes' => $this->notes,
            'region' => null,
            'public_ip' => null,
            'availability_zone' => null,
            'subnet_id' => null,
            'vpc_id' => null,
            'secondary_ips' => null,
            'termination_protection' => null,
        ];
    }
}
