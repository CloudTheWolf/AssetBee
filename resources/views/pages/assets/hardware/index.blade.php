<?php

use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\DeleteHardware;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Hardware')] class extends Component {
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $name = '';

    public string $asset_tag = '';

    public string $category = 'laptop';

    public string $createStatus = 'available';

    public string $serial_number = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $operating_system = '';

    public string $cpu = '';

    public string $ram_gb = '';

    public string $storage_gb = '';

    public string $bitlocker_status = '';

    public string $bitlocker_recovery_key = '';

    public bool $is_vm_host = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Hardware::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        if (! $this->selectedCategory()?->canBeVmHost()) {
            $this->is_vm_host = false;
        }

        if (! $this->selectedCategory()?->hasComputeSpecs()) {
            $this->resetComputeFields();
        }
    }

    public function updatedOperatingSystem(): void
    {
        if (! $this->selectedOperatingSystem()?->isWindows()) {
            $this->bitlocker_status = '';
            $this->bitlocker_recovery_key = '';
        }
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function create(CreateHardware $createHardware): void
    {
        $this->authorize('create', Hardware::class);

        $createHardware->handle(CurrentOrganization::require(), $this->createPayload());

        $this->reset([
            'name',
            'asset_tag',
            'serial_number',
            'manufacturer',
            'model',
            'operating_system',
            'cpu',
            'ram_gb',
            'storage_gb',
            'bitlocker_status',
            'bitlocker_recovery_key',
            'is_vm_host',
        ]);
        $this->category = HardwareCategory::Laptop->value;
        $this->createStatus = HardwareStatus::Available->value;

        Flux::modal('create-hardware')->close();
        Flux::toast(variant: 'success', text: __('Hardware created.'));
    }

    public function delete(Hardware $hardware, DeleteHardware $deleteHardware): void
    {
        $this->authorize('delete', $hardware);
        $deleteHardware->handle($hardware);
        Flux::toast(variant: 'success', text: __('Hardware deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function createPayload(): array
    {
        return [
            'name' => $this->name,
            'asset_tag' => $this->asset_tag !== '' ? $this->asset_tag : null,
            'serial_number' => $this->serial_number !== '' ? $this->serial_number : null,
            'manufacturer' => $this->manufacturer !== '' ? $this->manufacturer : null,
            'model' => $this->model !== '' ? $this->model : null,
            'category' => $this->category,
            'status' => $this->createStatus,
            'operating_system' => $this->operating_system !== '' ? $this->operating_system : null,
            'cpu' => $this->cpu !== '' ? $this->cpu : null,
            'ram_gb' => $this->ram_gb !== '' ? (int) $this->ram_gb : null,
            'storage_gb' => $this->storage_gb !== '' ? (int) $this->storage_gb : null,
            'bitlocker_status' => $this->bitlocker_status !== '' ? $this->bitlocker_status : null,
            'bitlocker_recovery_key' => $this->bitlocker_recovery_key !== '' ? $this->bitlocker_recovery_key : null,
            'is_vm_host' => $this->is_vm_host,
        ];
    }

    protected function resetComputeFields(): void
    {
        $this->reset([
            'serial_number',
            'manufacturer',
            'model',
            'operating_system',
            'cpu',
            'ram_gb',
            'storage_gb',
            'bitlocker_status',
            'bitlocker_recovery_key',
            'is_vm_host',
        ]);
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
    public function hardwares()
    {
        $sortable = ['name', 'asset_tag', 'category', 'status'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';

        return Hardware::query()
            ->with('assignedUserware')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('asset_tag', 'like', '%'.$this->search.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy($sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Hardware') }}</flux:heading>
            <flux:text>{{ __('Physical devices across your organization.') }}</flux:text>
        </div>
        @can('create', App\Models\Hardware::class)
            <flux:modal.trigger name="create-hardware">
                <flux:button variant="primary" icon="plus">{{ __('Add hardware') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, asset tag, serial...')" class="flex-1" />
        <flux:select wire:model.live="status" class="sm:w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (App\Enums\HardwareStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->hardwares">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'asset_tag'" :direction="$sortDirection" wire:click="sort('asset_tag')">{{ __('Asset tag') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'category'" :direction="$sortDirection" wire:click="sort('category')">{{ __('Category') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->hardwares as $hardware)
                <flux:table.row :key="$hardware->id">
                    <flux:table.cell>
                        <a href="{{ route('assets.hardware.show', $hardware) }}" class="font-medium text-accent">{{ $hardware->name }}</a>
                        <div class="text-xs text-zinc-500">
                            {{ collect([
                                $hardware->manufacturer,
                                $hardware->model,
                                $hardware->operating_system?->label(),
                                $hardware->is_vm_host ? __('VM host') : null,
                                $hardware->inventory_collected_at
                                    ? __('Inventory :when', ['when' => $hardware->inventory_collected_at->diffForHumans()])
                                    : null,
                            ])->filter()->implode(' · ') }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $hardware->asset_tag ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $hardware->category->label() }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$hardware->status->color()">{{ $hardware->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $hardware->assignedUserware?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.hardware.show', $hardware)" icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $hardware)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $hardware->id }})" wire:confirm="{{ __('Delete this hardware?') }}">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <div class="py-10 text-center">
                            <flux:heading size="sm">{{ __('No hardware found') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a device to start tracking physical assets.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="create-hardware" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add hardware') }}</flux:heading>
                <flux:text>{{ __('Start with the basics. Specs appear based on the type.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:select wire:model.live="category" :label="__('Type')">
                @foreach (App\Enums\HardwareCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="asset_tag" :label="__('Asset tag')" />
            <flux:select wire:model="createStatus" :label="__('Status')">
                @foreach (App\Enums\HardwareStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>

            @if ($this->selectedCategory()?->hasComputeSpecs())
                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Device details') }}</flux:heading>
                    <flux:input wire:model="manufacturer" :label="__('Manufacturer')" />
                    <flux:input wire:model="model" :label="__('Model')" />
                    <flux:input wire:model="serial_number" :label="__('Serial number')" />
                    <flux:select wire:model.live="operating_system" :label="__('Operating system')">
                        <option value="">{{ __('Select OS') }}</option>
                        @foreach (App\Enums\HardwareOperatingSystem::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </flux:select>
                    <div class="grid grid-cols-3 gap-3">
                        <flux:input wire:model="cpu" :label="__('CPU')" class="col-span-3 sm:col-span-1" />
                        <flux:input wire:model="ram_gb" type="number" min="1" :label="__('RAM (GB)')" />
                        <flux:input wire:model="storage_gb" type="number" min="1" :label="__('Storage (GB)')" />
                    </div>

                    @if ($this->selectedOperatingSystem()?->isWindows())
                        <div class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('BitLocker') }}</flux:heading>
                            <flux:select wire:model="bitlocker_status" :label="__('BitLocker status')">
                                <option value="">{{ __('Select status') }}</option>
                                @foreach (App\Enums\BitLockerStatus::cases() as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </flux:select>
                            <flux:textarea wire:model="bitlocker_recovery_key" :label="__('Recovery key')" rows="2" />
                        </div>
                    @endif

                    @if ($this->selectedCategory()?->canBeVmHost())
                        <flux:checkbox wire:model="is_vm_host" :label="__('VM host')" :description="__('Allow virtualware to run on this server.')" />
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
