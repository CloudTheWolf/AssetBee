<?php

use App\Actions\Assets\AssignHardware;
use App\Actions\Assets\DeleteHardware;
use App\Actions\Assets\UpdateHardware;
use App\Enums\HardwareCategory;
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

    public string $purchased_at = '';

    public string $notes = '';

    public string $assigned_userware_id = '';

    public function mount(Hardware $hardware): void
    {
        $this->authorize('view', $hardware);
        abort_unless($hardware->organization_id === CurrentOrganization::require()->id, 404);

        $this->hardware = $hardware->load('assignedUserware');
        $this->fillForm();
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
            'purchased_at' => $this->purchased_at !== '' ? $this->purchased_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load('assignedUserware');

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

        $this->hardware = $assignHardware->handle($this->hardware, $userware)->load('assignedUserware');
        $this->status = $this->hardware->status->value;

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function delete(DeleteHardware $deleteHardware): void
    {
        $this->authorize('delete', $this->hardware);
        $deleteHardware->handle($this->hardware);
        $this->redirect(route('assets.hardware.index', absolute: false), navigate: true);
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
                <flux:text>{{ $hardware->asset_tag ?? __('No asset tag') }}</flux:text>
            </div>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:input wire:model="asset_tag" :label="__('Asset tag')" @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:input wire:model="serial_number" :label="__('Serial number')" @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:input wire:model="manufacturer" :label="__('Manufacturer')" @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:input wire:model="model" :label="__('Model')" @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:select wire:model="category" :label="__('Category')" @disabled(! auth()->user()->can('update', $hardware))>
                @foreach (HardwareCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $hardware))>
                @foreach (HardwareStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="purchased_at" type="date" :label="__('Purchased at')" @disabled(! auth()->user()->can('update', $hardware)) />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $hardware)) />
            @can('update', $hardware)
                <div class="flex justify-between">
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
</div>
