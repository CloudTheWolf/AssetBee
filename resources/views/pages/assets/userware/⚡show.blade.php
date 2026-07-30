<?php

use App\Actions\Assets\DeleteUserware;
use App\Actions\Assets\UpdateUserware;
use App\Enums\UserwareStatus;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Userware')] class extends Component {
    use AuthorizesRequests;

    public Userware $userware;

    public string $name = '';

    public string $email = '';

    public string $employee_id = '';

    public string $department = '';

    public string $status = '';

    public string $notes = '';

    public function mount(Userware $userware): void
    {
        $this->authorize('view', $userware);

        abort_unless($userware->organization_id === CurrentOrganization::require()->id, 404);

        $this->userware = $userware;
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->userware->name;
        $this->email = $this->userware->email;
        $this->employee_id = (string) ($this->userware->employee_id ?? '');
        $this->department = (string) ($this->userware->department ?? '');
        $this->status = $this->userware->status->value;
        $this->notes = (string) ($this->userware->notes ?? '');
    }

    public function save(UpdateUserware $updateUserware): void
    {
        $this->authorize('update', $this->userware);

        $this->userware = $updateUserware->handle($this->userware, [
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id !== '' ? $this->employee_id : null,
            'department' => $this->department !== '' ? $this->department : null,
            'status' => $this->status,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        Flux::toast(variant: 'success', text: __('Identity updated.'));
    }

    public function delete(DeleteUserware $deleteUserware): void
    {
        $this->authorize('delete', $this->userware);

        $deleteUserware->handle($this->userware);

        $this->redirect(route('assets.userware.index', absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.userware.index')" wire:navigate icon="arrow-left">
            {{ __('Back') }}
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $userware->name }}</flux:heading>
            <flux:text>{{ $userware->email }}</flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $userware)) />
        <flux:input wire:model="email" type="email" :label="__('Email')" required @disabled(! auth()->user()->can('update', $userware)) />
        <flux:input wire:model="employee_id" :label="__('Employee ID')" @disabled(! auth()->user()->can('update', $userware)) />
        <flux:input wire:model="department" :label="__('Department')" @disabled(! auth()->user()->can('update', $userware)) />
        <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $userware))>
            @foreach (UserwareStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $userware)) />

        @can('update', $userware)
            <div class="flex justify-between">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this identity?') }}">
                    {{ __('Delete') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Assigned hardware') }}</flux:heading>
            <ul class="mt-3 space-y-2">
                @forelse ($userware->hardwares as $hardware)
                    <li>
                        <a href="{{ route('assets.hardware.show', $hardware) }}" class="text-accent" wire:navigate>{{ $hardware->name }}</a>
                    </li>
                @empty
                    <li><flux:text>{{ __('None') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Assigned virtualware') }}</flux:heading>
            <ul class="mt-3 space-y-2">
                @forelse ($userware->virtualwares as $virtualware)
                    <li>
                        <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="text-accent" wire:navigate>{{ $virtualware->name }}</a>
                    </li>
                @empty
                    <li><flux:text>{{ __('None') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
