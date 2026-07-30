<?php

use App\Actions\Assets\CreateVirtualware;
use App\Actions\Assets\DeleteVirtualware;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Virtualware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Virtualware')] class extends Component {
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $name = '';

    public string $provider = 'aws';

    public string $external_id = '';

    public string $category = 'vm';

    public string $createStatus = 'running';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Virtualware::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(CreateVirtualware $createVirtualware): void
    {
        $this->authorize('create', Virtualware::class);

        $createVirtualware->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_id' => $this->external_id !== '' ? $this->external_id : null,
            'category' => $this->category,
            'status' => $this->createStatus,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'external_id', 'notes']);
        $this->provider = VirtualwareProvider::Aws->value;
        $this->category = VirtualwareCategory::Vm->value;
        $this->createStatus = VirtualwareStatus::Running->value;

        Flux::modal('create-virtualware')->close();
        Flux::toast(variant: 'success', text: __('Virtualware created.'));
    }

    public function delete(Virtualware $virtualware, DeleteVirtualware $deleteVirtualware): void
    {
        $this->authorize('delete', $virtualware);
        $deleteVirtualware->handle($virtualware);
        Flux::toast(variant: 'success', text: __('Virtualware deleted.'));
    }

    #[Computed]
    public function virtualwares()
    {
        return Virtualware::query()
            ->with(['assignedUserware', 'hostHardware'])
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('external_id', 'like', '%'.$this->search.'%');
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
                <flux:heading size="xl">{{ __('Virtualware') }}</flux:heading>
                <flux:text>{{ __('VMs and cloud infrastructure.') }}</flux:text>
            </div>
            @can('create', App\Models\Virtualware::class)
                <flux:modal.trigger name="create-virtualware">
                    <flux:button variant="primary" icon="plus">{{ __('Add virtualware') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name or external ID...')" class="flex-1" />
            <flux:select wire:model.live="status" class="sm:w-48">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (App\Enums\VirtualwareStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Provider') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->virtualwares as $virtualware)
                        <flux:table.row :key="$virtualware->id">
                            <flux:table.cell>
                                <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $virtualware->provider->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $virtualware->category->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $virtualware->status->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $virtualware->assignedUserware?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('assets.virtualware.show', $virtualware)" wire:navigate>{{ __('View') }}</flux:button>
                                    @can('delete', $virtualware)
                                        <flux:button size="sm" variant="danger" wire:click="delete({{ $virtualware->id }})" wire:confirm="{{ __('Delete this virtualware?') }}">{{ __('Delete') }}</flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6"><flux:text class="py-6 text-center">{{ __('No virtualware found.') }}</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{ $this->virtualwares->links() }}

        <flux:modal name="create-virtualware" class="max-w-lg">
            <form wire:submit="create" class="space-y-6">
                <flux:heading size="lg">{{ __('Add virtualware') }}</flux:heading>
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:select wire:model="provider" :label="__('Provider')">
                    @foreach (App\Enums\VirtualwareProvider::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="external_id" :label="__('External ID')" />
                <flux:select wire:model="category" :label="__('Category')">
                    @foreach (App\Enums\VirtualwareCategory::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="createStatus" :label="__('Status')">
                    @foreach (App\Enums\VirtualwareStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
