<?php

use App\Actions\Assets\AssignHardware;
use App\Actions\Assets\DeleteHardware;
use App\Actions\Assets\UpdateHardware;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hardware')] class extends Component {
    use AuthorizesRequests;

    public Hardware $hardware;

    public string $name = '';

    public string $asset_tag = '';

    public string $serial_number = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $category = '';

    public string $status = '';

    public string $operating_system = '';

    public string $cpu = '';

    public string $ram_gb = '';

    public string $storage_gb = '';

    public string $bitlocker_status = '';

    public string $bitlocker_recovery_key = '';

    public bool $is_vm_host = false;

    public string $purchased_at = '';

    public string $notes = '';

    public string $assigned_userware_id = '';

    public function mount(Hardware $hardware): void
    {
        $this->authorize('view', $hardware);
        abort_unless($hardware->organization_id === CurrentOrganization::require()->id, 404);

        $this->hardware = $hardware->load(['assignedUserware', 'virtualwares']);
        $this->fillForm();
    }

    public function updatedCategory(): void
    {
        if (! $this->selectedCategory()?->canBeVmHost()) {
            $this->is_vm_host = false;
        }
    }

    public function updatedOperatingSystem(): void
    {
        if (! $this->selectedOperatingSystem()?->isWindows()) {
            $this->bitlocker_status = '';
            $this->bitlocker_recovery_key = '';
        }
    }

    public function fillForm(): void
    {
        $this->name = $this->hardware->name;
        $this->asset_tag = (string) ($this->hardware->asset_tag ?? '');
        $this->serial_number = (string) ($this->hardware->serial_number ?? '');
        $this->manufacturer = (string) ($this->hardware->manufacturer ?? '');
        $this->model = (string) ($this->hardware->model ?? '');
        $this->category = $this->hardware->category->value;
        $this->status = $this->hardware->status->value;
        $this->operating_system = $this->hardware->operating_system?->value ?? '';
        $this->cpu = (string) ($this->hardware->cpu ?? '');
        $this->ram_gb = $this->hardware->ram_gb !== null ? (string) $this->hardware->ram_gb : '';
        $this->storage_gb = $this->hardware->storage_gb !== null ? (string) $this->hardware->storage_gb : '';
        $this->bitlocker_status = $this->hardware->bitlocker_status?->value ?? '';
        $this->bitlocker_recovery_key = (string) ($this->hardware->bitlocker_recovery_key ?? '');
        $this->is_vm_host = (bool) $this->hardware->is_vm_host;
        $this->purchased_at = $this->hardware->purchased_at?->format('Y-m-d') ?? '';
        $this->notes = (string) ($this->hardware->notes ?? '');
        $this->assigned_userware_id = (string) ($this->hardware->assigned_userware_id ?? '');
    }

    public function save(UpdateHardware $updateHardware): void
    {
        $this->authorize('update', $this->hardware);

        $this->hardware = $updateHardware->handle($this->hardware, [
            'name' => $this->name,
            'asset_tag' => $this->asset_tag !== '' ? $this->asset_tag : null,
            'serial_number' => $this->serial_number !== '' ? $this->serial_number : null,
            'manufacturer' => $this->manufacturer !== '' ? $this->manufacturer : null,
            'model' => $this->model !== '' ? $this->model : null,
            'category' => $this->category,
            'status' => $this->status,
            'operating_system' => $this->operating_system !== '' ? $this->operating_system : null,
            'cpu' => $this->cpu !== '' ? $this->cpu : null,
            'ram_gb' => $this->ram_gb !== '' ? (int) $this->ram_gb : null,
            'storage_gb' => $this->storage_gb !== '' ? (int) $this->storage_gb : null,
            'bitlocker_status' => $this->bitlocker_status !== '' ? $this->bitlocker_status : null,
            'bitlocker_recovery_key' => $this->bitlocker_recovery_key !== '' ? $this->bitlocker_recovery_key : null,
            'is_vm_host' => $this->is_vm_host,
            'purchased_at' => $this->purchased_at !== '' ? $this->purchased_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load(['assignedUserware', 'virtualwares']);

        $this->fillForm();

        Flux::toast(variant: 'success', text: __('Hardware updated.'));
    }

    public function assign(AssignHardware $assignHardware): void
    {
        $this->authorize('assign', $this->hardware);

        $userware = $this->assigned_userware_id !== ''
            ? Userware::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->findOrFail($this->assigned_userware_id)
            : null;

        $this->hardware = $assignHardware->handle($this->hardware, $userware)->load(['assignedUserware', 'virtualwares']);
        $this->status = $this->hardware->status->value;

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function delete(DeleteHardware $deleteHardware): void
    {
        $this->authorize('delete', $this->hardware);
        $deleteHardware->handle($this->hardware);
        $this->redirect(route('assets.hardware.index', absolute: false), navigate: true);
    }

    protected function selectedCategory(): ?HardwareCategory
    {
        return HardwareCategory::tryFrom($this->category);
    }

    protected function selectedOperatingSystem(): ?HardwareOperatingSystem
    {
        return HardwareOperatingSystem::tryFrom($this->operating_system);
    }

    #[Computed]
    public function identities()
    {
        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.hardware.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $hardware->name }}</flux:heading>
            <flux:text>
                {{ $hardware->category->label() }}
                @if ($hardware->asset_tag)
                    · {{ $hardware->asset_tag }}
                @endif
                @if ($hardware->is_vm_host)
                    · {{ __('VM host') }}
                @endif
            </flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Basics') }}</flux:heading>
        <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $hardware)) />
        <flux:select wire:model.live="category" :label="__('Type')" @disabled(! auth()->user()->can('update', $hardware))>
            @foreach (HardwareCategory::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="asset_tag" :label="__('Asset tag')" @disabled(! auth()->user()->can('update', $hardware)) />
        <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $hardware))>
            @foreach (HardwareStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="purchased_at" type="date" :label="__('Purchased at')" @disabled(! auth()->user()->can('update', $hardware)) />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $hardware)) />

        @if ($this->selectedCategory()?->hasComputeSpecs())
            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Specs') }}</flux:heading>
                <flux:input wire:model="manufacturer" :label="__('Manufacturer')" @disabled(! auth()->user()->can('update', $hardware)) />
                <flux:input wire:model="model" :label="__('Model')" @disabled(! auth()->user()->can('update', $hardware)) />
                <flux:input wire:model="serial_number" :label="__('Serial number')" @disabled(! auth()->user()->can('update', $hardware)) />
                <flux:select wire:model.live="operating_system" :label="__('Operating system')" @disabled(! auth()->user()->can('update', $hardware))>
                    <option value="">{{ __('Select OS') }}</option>
                    @foreach (HardwareOperatingSystem::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="cpu" :label="__('CPU')" @disabled(! auth()->user()->can('update', $hardware)) />
                    <flux:input wire:model="ram_gb" type="number" min="1" :label="__('RAM (GB)')" @disabled(! auth()->user()->can('update', $hardware)) />
                    <flux:input wire:model="storage_gb" type="number" min="1" :label="__('Storage (GB)')" @disabled(! auth()->user()->can('update', $hardware)) />
                </div>
            </div>

            @if ($this->selectedOperatingSystem()?->isWindows())
                <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('BitLocker') }}</flux:heading>
                    <flux:select wire:model="bitlocker_status" :label="__('BitLocker status')" @disabled(! auth()->user()->can('update', $hardware))>
                        <option value="">{{ __('Select status') }}</option>
                        @foreach (BitLockerStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="bitlocker_recovery_key" :label="__('Recovery key')" rows="3" @disabled(! auth()->user()->can('update', $hardware)) />
                </div>
            @endif

            @if ($this->selectedCategory()?->canBeVmHost())
                <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Virtualization') }}</flux:heading>
                    <flux:checkbox wire:model="is_vm_host" :label="__('VM host')" :description="__('Virtualware can be assigned to this server.')" @disabled(! auth()->user()->can('update', $hardware)) />
                </div>
            @endif
        @endif

        @can('update', $hardware)
            <div class="flex justify-between border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this hardware?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    @can('assign', $hardware)
        <form wire:submit="assign" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Assignment') }}</flux:heading>
            <flux:select wire:model="assigned_userware_id" :label="__('Assigned identity')">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($this->identities as $identity)
                    <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Update assignment') }}</flux:button>
            </div>
        </form>
    @endcan

    @if ($hardware->is_vm_host)
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Hosted virtualware') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($hardware->virtualwares as $virtualware)
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                            <flux:text>{{ $virtualware->status->label() }}</flux:text>
                        </div>
                        <flux:button size="sm" :href="route('assets.virtualware.show', $virtualware)" wire:navigate>{{ __('View') }}</flux:button>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No virtualware hosted on this server.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
    @endif

    <livewire:asset-documents :documentable="$hardware" :key="'hardware-docs-'.$hardware->id" />
</div>
