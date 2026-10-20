<?php

use App\Actions\RemoteAdmin\SyncDeviceGroupHardware;
use App\Actions\RemoteAdmin\UpdateDeviceGroup;
use App\Models\DeviceGroup;
use App\Models\Hardware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Device group')] class extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public DeviceGroup $deviceGroup;

    public string $name = '';

    public string $description = '';

    /** @var list<int> */
    public array $hardware_ids = [];

    public string $hardwareSearch = '';

    public function mount(DeviceGroup $deviceGroup): void
    {
        $this->authorize('view', $deviceGroup);
        abort_unless($deviceGroup->organization_id === CurrentOrganization::require()->id, 404);

        $this->deviceGroup = $deviceGroup;
        $this->name = $deviceGroup->name;
        $this->description = (string) $deviceGroup->description;
        $this->hardware_ids = $deviceGroup->hardwares()->pluck('hardwares.id')->map(fn ($id) => (int) $id)->all();
    }

    public function save(UpdateDeviceGroup $updateDeviceGroup, SyncDeviceGroupHardware $syncDeviceGroupHardware): void
    {
        $this->authorize('update', $this->deviceGroup);

        $updateDeviceGroup->handle($this->deviceGroup, [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        $syncDeviceGroupHardware->handle($this->deviceGroup, [
            'hardware_ids' => $this->hardware_ids,
        ]);

        $this->deviceGroup->refresh();
        Flux::toast(variant: 'success', text: __('Device group updated.'));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->deviceGroup);
        $this->deviceGroup->delete();
        $this->redirect(route('assets.device-groups.index', absolute: false), navigate: true);
    }

    #[Computed]
    public function hardwareOptions()
    {
        return Hardware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->hardwareSearch !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->hardwareSearch.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->hardwareSearch.'%')
                        ->orWhere('asset_tag', 'like', '%'.$this->hardwareSearch.'%');
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'serial_number', 'asset_tag']);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $deviceGroup->name }}</flux:heading>
            <flux:text>{{ __('Manage membership for remote pushes.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button size="sm" :href="route('assets.device-groups.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
            @can('create', App\Models\RemotePush::class)
                <flux:button size="sm" variant="primary" :href="route('assets.remote-pushes.create', ['device_group_id' => $deviceGroup->id])" wire:navigate>
                    {{ __('Push…') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">{{ __('Members') }}</flux:heading>
            <flux:input wire:model.live.debounce.300ms="hardwareSearch" :placeholder="__('Filter hardware…')" icon="magnifying-glass" />
            <ul class="max-h-80 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse ($this->hardwareOptions as $hardware)
                    <li class="flex items-center gap-3 p-3" wire:key="hw-opt-{{ $hardware->id }}">
                        <flux:checkbox wire:model="hardware_ids" value="{{ $hardware->id }}" />
                        <div>
                            <div class="font-medium">{{ $hardware->name }}</div>
                            <flux:text class="text-sm">
                                {{ $hardware->serial_number ?: '—' }}
                                @if ($hardware->asset_tag)
                                    · {{ $hardware->asset_tag }}
                                @endif
                            </flux:text>
                        </div>
                    </li>
                @empty
                    <li class="p-3"><flux:text>{{ __('No matching hardware.') }}</flux:text></li>
                @endforelse
            </ul>
            <flux:text class="text-sm">{{ __(':count selected', ['count' => count($hardware_ids)]) }}</flux:text>
        </div>

        <div class="flex justify-between">
            @can('delete', $deviceGroup)
                <flux:button type="button" variant="danger" wire:click="delete" wire:confirm="{{ __('Delete this device group?') }}">
                    {{ __('Delete') }}
                </flux:button>
            @else
                <span></span>
            @endcan
            <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
