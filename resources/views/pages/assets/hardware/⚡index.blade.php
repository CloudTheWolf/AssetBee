<?php

use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\DeleteHardware;
use App\Enums\HardwareCategory;
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

    public string $name = '';

    public string $asset_tag = '';

    public string $serial_number = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $category = 'laptop';

    public string $createStatus = 'available';

    public string $purchased_at = '';

    public string $notes = '';

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

    public function create(CreateHardware $createHardware): void
    {
        $this->authorize('create', Hardware::class);

        $createHardware->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'asset_tag' => $this->asset_tag !== '' ? $this->asset_tag : null,
            'serial_number' => $this->serial_number !== '' ? $this->serial_number : null,
            'manufacturer' => $this->manufacturer !== '' ? $this->manufacturer : null,
            'model' => $this->model !== '' ? $this->model : null,
            'category' => $this->category,
            'status' => $this->createStatus,
            'purchased_at' => $this->purchased_at !== '' ? $this->purchased_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'asset_tag', 'serial_number', 'manufacturer', 'model', 'purchased_at', 'notes']);
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

    #[Computed]
    public function hardwares()
    {
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
            ->orderBy('name')
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

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Asset tag') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->hardwares as $hardware)
                        <flux:table.row :key="$hardware->id">
                            <flux:table.cell>
                                <a href="{{ route('assets.hardware.show', $hardware) }}" class="font-medium text-accent" wire:navigate>{{ $hardware->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $hardware->asset_tag ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $hardware->category->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $hardware->status->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $hardware->assignedUserware?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('assets.hardware.show', $hardware)" wire:navigate>{{ __('View') }}</flux:button>
                                    @can('delete', $hardware)
                                        <flux:button size="sm" variant="danger" wire:click="delete({{ $hardware->id }})" wire:confirm="{{ __('Delete this hardware?') }}">{{ __('Delete') }}</flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6"><flux:text class="py-6 text-center">{{ __('No hardware found.') }}</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{ $this->hardwares->links() }}

        <flux:modal name="create-hardware" class="max-w-lg">
            <form wire:submit="create" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add hardware') }}</flux:heading>
                </div>
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="asset_tag" :label="__('Asset tag')" />
                <flux:input wire:model="serial_number" :label="__('Serial number')" />
                <flux:input wire:model="manufacturer" :label="__('Manufacturer')" />
                <flux:input wire:model="model" :label="__('Model')" />
                <flux:select wire:model="category" :label="__('Category')">
                    @foreach (App\Enums\HardwareCategory::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="createStatus" :label="__('Status')">
                    @foreach (App\Enums\HardwareStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="purchased_at" type="date" :label="__('Purchased at')" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
