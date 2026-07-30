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
        <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $virtualware)) />
        <flux:select wire:model="provider" :label="__('Provider')" @disabled(! auth()->user()->can('update', $virtualware))>
            @foreach (VirtualwareProvider::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="external_id" :label="__('External ID')" @disabled(! auth()->user()->can('update', $virtualware)) />
        <flux:select wire:model="category" :label="__('Category')" @disabled(! auth()->user()->can('update', $virtualware))>
            @foreach (VirtualwareCategory::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $virtualware))>
            @foreach (VirtualwareStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $virtualware)) />
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
