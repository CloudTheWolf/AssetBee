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
            ->orderBy('name')
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

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Department') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->userwares as $userware)
                        <flux:table.row :key="$userware->id">
                            <flux:table.cell>
                                <a href="{{ route('assets.userware.show', $userware) }}" class="font-medium text-accent" wire:navigate>
                                    {{ $userware->name }}
                                </a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $userware->email }}</flux:table.cell>
                            <flux:table.cell>{{ $userware->department ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $userware->status->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('assets.userware.show', $userware)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                    @can('delete', $userware)
                                        <flux:button size="sm" variant="danger" wire:click="delete({{ $userware->id }})" wire:confirm="{{ __('Delete this identity?') }}">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <flux:text class="py-6 text-center">{{ __('No identities found.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{ $this->userwares->links() }}

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
