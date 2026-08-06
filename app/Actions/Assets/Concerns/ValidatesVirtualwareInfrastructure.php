<?php

namespace App\Actions\Assets\Concerns;

trait ValidatesVirtualwareInfrastructure
{
    /**
     * @return array<string, list<string>>
     */
    protected function infrastructureRules(): array
    {
        return [
            'region' => ['nullable', 'string', 'max:64'],
            'instance_type' => ['nullable', 'string', 'max:255'],
            'private_ip' => ['nullable', 'string', 'max:45'],
            'public_ip' => ['nullable', 'string', 'max:45'],
            'availability_zone' => ['nullable', 'string', 'max:64'],
            'subnet_id' => ['nullable', 'string', 'max:255'],
            'vpc_id' => ['nullable', 'string', 'max:255'],
            'secondary_ips' => ['nullable', 'array'],
            'secondary_ips.*.private_ip' => ['required', 'ip'],
            'secondary_ips.*.public_ip' => ['nullable', 'ip'],
            'secondary_ips.*.network_interface_id' => ['nullable', 'string', 'max:255'],
            'disks' => ['nullable', 'array'],
            'disks.*.device_name' => ['nullable', 'string', 'max:255'],
            'disks.*.volume_id' => ['nullable', 'string', 'max:255'],
            'disks.*.size_gb' => ['nullable', 'integer', 'min:1'],
            'disks.*.volume_type' => ['nullable', 'string', 'max:64'],
            'disks.*.encrypted' => ['nullable', 'boolean'],
            'disks.*.delete_on_termination' => ['nullable', 'boolean'],
            'termination_protection' => ['nullable', 'boolean'],
        ];
    }
}
