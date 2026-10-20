<?php

use App\Actions\RemoteAdmin\CreateDeviceGroup;
use App\Models\DeviceGroup;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Device groups')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DeviceGroup::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(CreateDeviceGroup $createDeviceGroup): void
    {
        $this->authorize('create', DeviceGroup::class);

        $group = $createDeviceGroup->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        $this->reset(['name', 'description']);
        Flux::modal('create-device-group')->close();
        Flux::toast(variant: 'success', text: __('Device group created.'));

        $this->redirect(route('assets.device-groups.show', $group, absolute: false), navigate: true);
    }

    public function delete(DeviceGroup $deviceGroup): void
    {
        $this->authorize('delete', $deviceGroup);
        $deviceGroup->delete();
        Flux::toast(variant: 'success', text: __('Device group deleted.'));
    }

    #[Computed]
    public function groups()
    {
        return DeviceGroup::query()
            ->withCount('hardwares')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Device groups') }}</flux:heading>
            <flux:text>{{ __('Target collections of hardware for remote pushes.') }}</flux:text>
        </div>
        @can('create', App\Models\DeviceGroup::class)
            <flux:modal.trigger name="create-device-group">
                <flux:button variant="primary" icon="plus">{{ __('Add group') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search groups…')" icon="magnifying-glass" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Devices') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->groups as $group)
                <flux:table.row wire:key="device-group-{{ $group->id }}">
                    <flux:table.cell>
                        <a href="{{ route('assets.device-groups.show', $group) }}" class="font-medium text-accent" wire:navigate>{{ $group->name }}</a>
                        @if ($group->description)
                            <flux:text class="mt-1 text-sm">{{ $group->description }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $group->hardwares_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" :href="route('assets.device-groups.show', $group)" wire:navigate>{{ __('Open') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">{{ __('No device groups yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->groups->links() }}

    <flux:modal name="create-device-group" class="md:w-96">
        <form wire:submit="create" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('New device group') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
