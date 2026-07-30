<?php

use App\Actions\Assets\AssignVirtualware;
use App\Actions\Assets\DeleteVirtualware;
use App\Actions\Assets\UpdateVirtualware;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
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

    public string $host_hardware_id = '';

    public function mount(Virtualware $virtualware): void
    {
        $this->authorize('view', $virtualware);
        abort_unless($virtualware->organization_id === CurrentOrganization::require()->id, 404);

        $this->virtualware = $virtualware->load(['assignedUserware', 'hostHardware']);
        $this->fillForm();
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
            'host_hardware_id' => $this->host_hardware_id !== '' ? (int) $this->host_hardware_id : null,
            'assigned_userware_id' => $this->assigned_userware_id !== '' ? (int) $this->assigned_userware_id : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load(['assignedUserware', 'hostHardware']);

        Flux::toast(variant: 'success', text: __('Virtualware updated.'));
    }

    public function assign(AssignVirtualware $assignVirtualware): void
    {
        $this->authorize('assign', $this->virtualware);

        $userware = $this->assigned_userware_id !== ''
            ? Userware::query()->where('organization_id', CurrentOrganization::require()->id)->findOrFail($this->assigned_userware_id)
            : null;

        $host = $this->host_hardware_id !== ''
            ? Hardware::query()->where('organization_id', CurrentOrganization::require()->id)->findOrFail($this->host_hardware_id)
            : null;

        $this->virtualware = $assignVirtualware
            ->handle($this->virtualware, $userware, $host, updateHost: true)
            ->load(['assignedUserware', 'hostHardware']);

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
        return Hardware::query()->where('organization_id', CurrentOrganization::require()->id)->orderBy('name')->get();
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
                <flux:select wire:model="host_hardware_id" :label="__('Host hardware')">
                    <option value="">{{ __('No host') }}</option>
                    @foreach ($this->hosts as $host)
                        <option value="{{ $host->id }}">{{ $host->name }}</option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('Update assignments') }}</flux:button>
                </div>
            </form>
        @endcan
</div>
