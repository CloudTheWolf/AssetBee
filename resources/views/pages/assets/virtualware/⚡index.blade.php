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

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

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

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
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
        $sortable = ['name', 'provider', 'category', 'status'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';

        return Virtualware::query()
            ->with(['assignedUserware', 'hostHardware', 'cloudTenant'])
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('external_id', 'like', '%'.$this->search.'%');
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

    <flux:table :paginate="$this->virtualwares">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'provider'" :direction="$sortDirection" wire:click="sort('provider')">{{ __('Provider') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'category'" :direction="$sortDirection" wire:click="sort('category')">{{ __('Category') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Placement') }}</flux:table.column>
            <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->virtualwares as $virtualware)
                <flux:table.row :key="$virtualware->id">
                    <flux:table.cell>
                        <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                        @if ($virtualware->external_id)
                            <div class="text-xs text-zinc-500">{{ $virtualware->external_id }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $virtualware->provider->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $virtualware->category->label() }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$virtualware->status->color()">{{ $virtualware->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($virtualware->cloudTenant)
                            {{ $virtualware->cloudTenant->name }}
                            <div class="text-xs text-zinc-500">{{ __('Cloud tenant') }}</div>
                        @elseif ($virtualware->hostHardware)
                            {{ $virtualware->hostHardware->name }}
                            <div class="text-xs text-zinc-500">{{ __('VM host') }}</div>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $virtualware->assignedUserware?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.virtualware.show', $virtualware)" wire:navigate icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $virtualware)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $virtualware->id }})" wire:confirm="{{ __('Delete this virtualware?') }}">
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
                    <flux:table.cell colspan="7">
                        <div class="py-10 text-center">
                            <flux:heading size="sm">{{ __('No virtualware found') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a VM or cloud resource to track it here.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
