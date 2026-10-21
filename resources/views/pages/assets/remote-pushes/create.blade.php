<?php

use App\Actions\RemoteAdmin\CreateRemotePush;
use App\Enums\RemotePushType;
use App\Models\DeviceGroup;
use App\Models\Hardware;
use App\Models\RemotePackage;
use App\Models\RemotePush;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('New remote push')] class extends Component
{
    use AuthorizesRequests;

    public string $type = 'bitlocker_status';

    /** @var list<int|string> */
    public array $remote_package_ids = [];

    #[Url]
    public string $device_group_id = '';

    #[Url]
    public string $hardware_id = '';

    /** @var list<int|string> */
    public array $hardware_ids = [];

    public string $notes = '';

    public string $hardwareSearch = '';

    public function mount(): void
    {
        $this->authorize('create', RemotePush::class);

        if ($this->device_group_id !== '') {
            $group = DeviceGroup::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->find($this->device_group_id);
            if ($group === null) {
                $this->device_group_id = '';
            }
        }

        if ($this->hardware_id !== '') {
            $hardware = Hardware::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->find($this->hardware_id);
            if ($hardware !== null) {
                $this->hardware_ids = [(int) $hardware->id];
            }
        }
    }

    public function create(CreateRemotePush $createRemotePush): void
    {
        $this->authorize('create', RemotePush::class);

        $push = $createRemotePush->handle(
            CurrentOrganization::require(),
            auth()->user(),
            [
                'type' => $this->type,
                'remote_package_ids' => array_map('intval', $this->remote_package_ids),
                'device_group_id' => $this->device_group_id !== '' ? (int) $this->device_group_id : null,
                'hardware_ids' => array_map('intval', $this->hardware_ids),
                'notes' => $this->notes !== '' ? $this->notes : null,
            ],
        );

        Flux::toast(variant: 'success', text: __('Remote push created.'));
        $this->redirect(route('assets.remote-pushes.show', $push, absolute: false), navigate: true);
    }

    #[Computed]
    public function packages()
    {
        return RemotePackage::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function groups()
    {
        return DeviceGroup::query()
            ->withCount('hardwares')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hardwareOptions()
    {
        return Hardware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->hardwareSearch !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->hardwareSearch.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->hardwareSearch.'%');
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'serial_number']);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('New remote push') }}</flux:heading>
        <flux:text>{{ __('Send a probe or curated install to devices or a group.') }}</flux:text>
    </div>

    <form wire:submit="create" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:select wire:model.live="type" :label="__('Command')">
            @foreach (App\Enums\RemotePushType::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>

        @if ($type === 'install_package')
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">{{ __('Packages') }}</flux:heading>
                <flux:text>{{ __('Select one or more packages to install in order.') }}</flux:text>
                <ul class="max-h-64 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($this->packages as $package)
                        <li class="flex items-center gap-3 p-3" wire:key="push-pkg-{{ $package->id }}">
                            <flux:checkbox wire:model="remote_package_ids" value="{{ $package->id }}" />
                            <div>
                                <div class="font-medium">{{ $package->name }}</div>
                                <flux:text class="text-sm">{{ $package->source->label() }}</flux:text>
                            </div>
                        </li>
                    @empty
                        <li class="p-3">
                            <flux:text>
                                {{ __('No packages yet.') }}
                                <a href="{{ route('assets.remote-packages.index') }}" class="text-accent" wire:navigate>{{ __('Add one') }}</a>
                            </flux:text>
                        </li>
                    @endforelse
                </ul>
            </div>
        @endif

        <flux:select wire:model="device_group_id" :label="__('Device group (optional)')">
            <option value="">{{ __('None') }}</option>
            @foreach ($this->groups as $group)
                <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->hardwares_count }})</option>
            @endforeach
        </flux:select>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">{{ __('Additional devices') }}</flux:heading>
            <flux:input wire:model.live.debounce.300ms="hardwareSearch" :placeholder="__('Filter hardware…')" />
            <ul class="max-h-64 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->hardwareOptions as $hardware)
                    <li class="flex items-center gap-3 p-3" wire:key="push-hw-{{ $hardware->id }}">
                        <flux:checkbox wire:model="hardware_ids" value="{{ $hardware->id }}" />
                        <div>
                            <div class="font-medium">{{ $hardware->name }}</div>
                            <flux:text class="text-sm">{{ $hardware->serial_number ?: '—' }}</flux:text>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex justify-end gap-2">
            <flux:button :href="route('assets.remote-pushes.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __('Create push') }}</flux:button>
        </div>
    </form>
</div>
