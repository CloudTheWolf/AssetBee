<?php

use App\Actions\Assets\CreateUserware;
use App\Actions\Assets\DeleteUserware;
use App\Enums\UserwareStatus;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Userware')] class extends Component {
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $name = '';

    public string $email = '';

    public string $employee_id = '';

    public string $department = '';

    public string $createStatus = 'active';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Userware::class);
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

    public function create(CreateUserware $createUserware): void
    {
        $this->authorize('create', Userware::class);

        $createUserware->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id !== '' ? $this->employee_id : null,
            'department' => $this->department !== '' ? $this->department : null,
            'status' => $this->createStatus,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'email', 'employee_id', 'department', 'notes']);
        $this->createStatus = UserwareStatus::Active->value;

        Flux::modal('create-userware')->close();
        Flux::toast(variant: 'success', text: __('Identity created.'));
    }

    public function delete(Userware $userware, DeleteUserware $deleteUserware): void
    {
        $this->authorize('delete', $userware);

        $deleteUserware->handle($userware);

        Flux::toast(variant: 'success', text: __('Identity deleted.'));
    }

    #[Computed]
    public function userwares()
    {
        $sortable = ['name', 'email', 'department', 'status'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';

        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('employee_id', 'like', '%'.$this->search.'%');
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
            <flux:heading size="xl">{{ __('Userware') }}</flux:heading>
            <flux:text>{{ __('Managed identities across your organization.') }}</flux:text>
        </div>

        @can('create', App\Models\Userware::class)
            <flux:modal.trigger name="create-userware">
                <flux:button variant="primary" icon="plus" data-test="create-userware">{{ __('Add identity') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, email, employee ID...')" class="flex-1" />
        <flux:select wire:model.live="status" class="sm:w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (App\Enums\UserwareStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->userwares">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'department'" :direction="$sortDirection" wire:click="sort('department')">{{ __('Department') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->userwares as $userware)
                <flux:table.row :key="$userware->id">
                    <flux:table.cell>
                        <a href="{{ route('assets.userware.show', $userware) }}" class="font-medium text-accent" wire:navigate>
                            {{ $userware->name }}
                        </a>
                        @if ($userware->employee_id)
                            <div class="text-xs text-zinc-500">{{ $userware->employee_id }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $userware->email }}</flux:table.cell>
                    <flux:table.cell>{{ $userware->department ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$userware->status->color()">{{ $userware->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.userware.show', $userware)" wire:navigate icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $userware)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $userware->id }})" wire:confirm="{{ __('Delete this identity?') }}">
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
                    <flux:table.cell colspan="5">
                        <div class="py-10 text-center">
                            <flux:heading size="sm">{{ __('No identities found') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a managed identity to assign assets.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('create', App\Models\Userware::class)
        <flux:modal name="create-userware" class="max-w-lg">
            <form wire:submit="create" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add identity') }}</flux:heading>
                    <flux:text>{{ __('Create a managed identity for assignments.') }}</flux:text>
                </div>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="email" type="email" :label="__('Email')" required />
                <flux:input wire:model="employee_id" :label="__('Employee ID')" />
                <flux:input wire:model="department" :label="__('Department')" />
                <flux:select wire:model="createStatus" :label="__('Status')">
                    @foreach (App\Enums\UserwareStatus::cases() as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
