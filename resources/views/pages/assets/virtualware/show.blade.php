<?php

use App\Actions\Assets\AssignVirtualware;
use App\Actions\Assets\DeleteVirtualware;
use App\Actions\Assets\UpdateVirtualware;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Userware;
use App\Models\Virtualware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Virtualware')] class extends Component {
    use AuthorizesRequests;

    public Virtualware $virtualware;

    public string $name = '';

    public string $provider = '';

    public string $external_id = '';

    public string $category = '';

    public string $status = '';

    public string $notes = '';

    public string $region = '';

    public string $instance_type = '';

    public string $private_ip = '';

    public string $public_ip = '';

    public string $availability_zone = '';

    public string $subnet_id = '';

    public string $vpc_id = '';

    public string $termination_protection = '';

    public string $assigned_userware_id = '';

    public string $placement = 'none';

    public string $host_hardware_id = '';

    public string $cloud_tenant_id = '';

    public function mount(Virtualware $virtualware): void
    {
        $this->authorize('view', $virtualware);
        abort_unless($virtualware->organization_id === CurrentOrganization::require()->id, 404);

        $this->virtualware = $virtualware->load(['assignedUserware', 'hostHardware', 'cloudTenant']);
        $this->fillForm();
    }

    public function updatedPlacement(string $value): void
    {
        if ($value !== 'vm_host') {
            $this->host_hardware_id = '';
        }

        if ($value !== 'cloud_tenant') {
            $this->cloud_tenant_id = '';
        }
    }

    public function fillForm(): void
    {
        $this->name = $this->virtualware->name;
        $this->provider = $this->virtualware->provider->value;
        $this->external_id = (string) ($this->virtualware->external_id ?? '');
        $this->category = $this->virtualware->category->value;
        $this->status = $this->virtualware->status->value;
        $this->notes = (string) ($this->virtualware->notes ?? '');
        $this->region = (string) ($this->virtualware->region ?? '');
        $this->instance_type = (string) ($this->virtualware->instance_type ?? '');
        $this->private_ip = (string) ($this->virtualware->private_ip ?? '');
        $this->public_ip = (string) ($this->virtualware->public_ip ?? '');
        $this->availability_zone = (string) ($this->virtualware->availability_zone ?? '');
        $this->subnet_id = (string) ($this->virtualware->subnet_id ?? '');
        $this->vpc_id = (string) ($this->virtualware->vpc_id ?? '');
        $this->termination_protection = match ($this->virtualware->termination_protection) {
            true => '1',
            false => '0',
            default => '',
        };
        $this->assigned_userware_id = (string) ($this->virtualware->assigned_userware_id ?? '');
        $this->host_hardware_id = (string) ($this->virtualware->host_hardware_id ?? '');
        $this->cloud_tenant_id = (string) ($this->virtualware->cloud_tenant_id ?? '');

        $this->placement = match (true) {
            $this->virtualware->host_hardware_id !== null => 'vm_host',
            $this->virtualware->cloud_tenant_id !== null => 'cloud_tenant',
            default => 'none',
        };
    }

    public function save(UpdateVirtualware $updateVirtualware): void
    {
        $this->authorize('update', $this->virtualware);

        $this->virtualware = $updateVirtualware->handle($this->virtualware, [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_id' => $this->external_id !== '' ? $this->external_id : null,
            'category' => $this->category,
            'status' => $this->status,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'region' => $this->region !== '' ? $this->region : null,
            'instance_type' => $this->instance_type !== '' ? $this->instance_type : null,
            'private_ip' => $this->private_ip !== '' ? $this->private_ip : null,
            'public_ip' => $this->public_ip !== '' ? $this->public_ip : null,
            'availability_zone' => $this->availability_zone !== '' ? $this->availability_zone : null,
            'subnet_id' => $this->subnet_id !== '' ? $this->subnet_id : null,
            'vpc_id' => $this->vpc_id !== '' ? $this->vpc_id : null,
            'disks' => $this->virtualware->disks,
            'termination_protection' => match ($this->termination_protection) {
                '1' => true,
                '0' => false,
                default => null,
            },
            'assigned_userware_id' => $this->virtualware->assigned_userware_id,
            'host_hardware_id' => $this->virtualware->host_hardware_id,
            'cloud_tenant_id' => $this->virtualware->cloud_tenant_id,
        ])->load(['assignedUserware', 'hostHardware', 'cloudTenant']);

        $this->fillForm();

        Flux::toast(variant: 'success', text: __('Virtualware updated.'));
    }

    public function assign(AssignVirtualware $assignVirtualware): void
    {
        $this->authorize('assign', $this->virtualware);

        $userware = $this->assigned_userware_id !== ''
            ? Userware::query()->where('organization_id', CurrentOrganization::require()->id)->findOrFail($this->assigned_userware_id)
            : null;

        $host = $this->placement === 'vm_host' && $this->host_hardware_id !== ''
            ? Hardware::query()->where('organization_id', CurrentOrganization::require()->id)->findOrFail($this->host_hardware_id)
            : null;

        $tenant = $this->placement === 'cloud_tenant' && $this->cloud_tenant_id !== ''
            ? CloudTenant::query()->where('organization_id', CurrentOrganization::require()->id)->findOrFail($this->cloud_tenant_id)
            : null;

        $this->virtualware = $assignVirtualware
            ->handle(
                $this->virtualware,
                $userware,
                $host,
                updateHost: true,
                cloudTenant: $tenant,
                updateCloudTenant: true,
            )
            ->load(['assignedUserware', 'hostHardware', 'cloudTenant']);

        $this->fillForm();

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function delete(DeleteVirtualware $deleteVirtualware): void
    {
        $this->authorize('delete', $this->virtualware);
        $deleteVirtualware->handle($this->virtualware);
        $this->redirect(route('assets.virtualware.index', absolute: false), navigate: true);
    }

    #[Computed]
    public function identities()
    {
        return Userware::query()->where('organization_id', CurrentOrganization::require()->id)->orderBy('name')->get();
    }

    #[Computed]
    public function hosts()
    {
        return Hardware::query()
            ->vmHosts()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function tenants()
    {
        return CloudTenant::query()->where('organization_id', CurrentOrganization::require()->id)->orderBy('name')->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.virtualware.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $virtualware->name }}</flux:heading>
            <flux:text>{{ $virtualware->provider->label() }} · {{ $virtualware->category->label() }}</flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required :disabled="! auth()->user()->can('update', $virtualware)" />
        <flux:select wire:model="provider" :label="__('Provider')" :disabled="! auth()->user()->can('update', $virtualware)">
            @foreach (VirtualwareProvider::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="external_id" :label="__('External ID')" :disabled="! auth()->user()->can('update', $virtualware)" />
        <flux:select wire:model="category" :label="__('Category')" :disabled="! auth()->user()->can('update', $virtualware)">
            @foreach (VirtualwareCategory::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model="status" :label="__('Status')" :disabled="! auth()->user()->can('update', $virtualware)">
            @foreach (VirtualwareStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>

        <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Infrastructure') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="instance_type" :label="__('Type')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:input wire:model="region" :label="__('Region')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:input wire:model="availability_zone" :label="__('Zone')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:select wire:model="termination_protection" :label="__('Termination protection')" :disabled="! auth()->user()->can('update', $virtualware)">
                    <option value="">{{ __('Unknown') }}</option>
                    <option value="1">{{ __('Enabled') }}</option>
                    <option value="0">{{ __('Disabled') }}</option>
                </flux:select>
                <flux:input wire:model="private_ip" :label="__('Private IP')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:input wire:model="public_ip" :label="__('Public IP')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:input wire:model="vpc_id" :label="__('VPC / VNet')" :disabled="! auth()->user()->can('update', $virtualware)" />
                <flux:input wire:model="subnet_id" :label="__('Subnet')" :disabled="! auth()->user()->can('update', $virtualware)" />
            </div>

            @if (! empty($virtualware->secondary_ips))
                <div class="space-y-2">
                    <flux:heading size="sm">{{ __('Secondary IPs') }}</flux:heading>
                    <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($virtualware->secondary_ips as $address)
                            <li class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-medium">{{ $address['private_ip'] }}</div>
                                    @if (! empty($address['public_ip']))
                                        <flux:text>{{ __('Public: :ip', ['ip' => $address['public_ip']]) }}</flux:text>
                                    @endif
                                </div>
                                @if (! empty($address['network_interface_id']))
                                    <flux:text>{{ $address['network_interface_id'] }}</flux:text>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-2">
                <flux:heading size="sm">{{ __('Disks') }}</flux:heading>
                @if (! empty($virtualware->disks))
                    <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($virtualware->disks as $disk)
                            <li class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="font-medium">{{ $disk['device_name'] ?? __('Disk') }}</div>
                                    <flux:text>
                                        {{ $disk['volume_id'] ?? '—' }}
                                        @if (! empty($disk['volume_type']))
                                            · {{ $disk['volume_type'] }}
                                        @endif
                                    </flux:text>
                                </div>
                                <flux:text>
                                    {{ isset($disk['size_gb']) ? __(':size GB', ['size' => $disk['size_gb']]) : '—' }}
                                    @if (array_key_exists('encrypted', $disk) && $disk['encrypted'] !== null)
                                        · {{ $disk['encrypted'] ? __('Encrypted') : __('Not encrypted') }}
                                    @endif
                                    @if (array_key_exists('delete_on_termination', $disk) && $disk['delete_on_termination'] !== null)
                                        · {{ $disk['delete_on_termination'] ? __('Delete on termination') : __('Retain on termination') }}
                                    @endif
                                </flux:text>
                            </li>
                        @endforeach
                    </ul>
                    @if ($virtualware->totalDiskSizeGb() !== null)
                        <flux:text>{{ __('Total: :size GB', ['size' => $virtualware->totalDiskSizeGb()]) }}</flux:text>
                    @endif
                @else
                    <flux:text>{{ __('No disk details recorded. Re-import from the cloud tenant to populate disks.') }}</flux:text>
                @endif
            </div>
        </div>

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :disabled="! auth()->user()->can('update', $virtualware)" />
        @can('update', $virtualware)
            <div class="flex justify-between">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this virtualware?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    @can('assign', $virtualware)
        <form wire:submit="assign" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Assignments') }}</flux:heading>
            <flux:select wire:model="assigned_userware_id" :label="__('Assigned identity')">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($this->identities as $identity)
                    <option value="{{ $identity->id }}">{{ $identity->name }}</option>
                @endforeach
            </flux:select>

            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Placement') }}</flux:heading>
                <flux:text>{{ __('Link this item to a cloud tenant or a hardware VM host — not both.') }}</flux:text>

                <flux:radio.group wire:model.live="placement" :label="__('Hosted on')">
                    <flux:radio value="none" :label="__('None')" />
                    <flux:radio value="cloud_tenant" :label="__('Cloud tenant')" />
                    <flux:radio value="vm_host" :label="__('Hardware VM host')" />
                </flux:radio.group>

                @if ($placement === 'cloud_tenant')
                    <flux:select wire:model="cloud_tenant_id" :label="__('Cloud tenant')">
                        <option value="">{{ __('Select tenant') }}</option>
                        @foreach ($this->tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($placement === 'vm_host')
                    <flux:select wire:model="host_hardware_id" :label="__('VM host')">
                        <option value="">{{ __('Select VM host') }}</option>
                        @foreach ($this->hosts as $host)
                            <option value="{{ $host->id }}">{{ $host->name }}</option>
                        @endforeach
                    </flux:select>
                    @if ($this->hosts->isEmpty())
                        <flux:text>{{ __('Mark a server as a VM host under Hardware first.') }}</flux:text>
                    @endif
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Update assignments') }}</flux:button>
            </div>
        </form>
    @endcan
</div>
