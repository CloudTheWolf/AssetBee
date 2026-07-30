<?php

use App\Actions\Assets\CreateUserwareAccount;
use App\Actions\Assets\DeleteUserware;
use App\Actions\Assets\DeleteUserwareAccount;
use App\Actions\Assets\UpdateUserware;
use App\Enums\UserwareStatus;
use App\Models\Software;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
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

    public string $account_type = 'software';

    public string $account_software_id = '';

    public string $account_site_name = '';

    public string $account_site_url = '';

    public string $account_username = '';

    public string $account_notes = '';

    public function mount(Userware $userware): void
    {
        $this->authorize('view', $userware);

        abort_unless($userware->organization_id === CurrentOrganization::require()->id, 404);

        $this->userware = $userware->load([
            'hardwares',
            'virtualwares',
            'softwareAssignments.software',
            'accounts.software',
        ]);
        $this->fillForm();
    }

    public function updatedAccountType(): void
    {
        if ($this->account_type === 'software') {
            $this->account_site_name = '';
            $this->account_site_url = '';
        } else {
            $this->account_software_id = '';
        }
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
        ])->load([
            'hardwares',
            'virtualwares',
            'softwareAssignments.software',
            'accounts.software',
        ]);

        Flux::toast(variant: 'success', text: __('Identity updated.'));
    }

    public function addAccount(CreateUserwareAccount $createUserwareAccount): void
    {
        $this->authorize('create', [UserwareAccount::class, $this->userware]);

        $createUserwareAccount->handle($this->userware, [
            'software_id' => $this->account_type === 'software' && $this->account_software_id !== ''
                ? (int) $this->account_software_id
                : null,
            'site_name' => $this->account_type === 'external' && $this->account_site_name !== ''
                ? $this->account_site_name
                : null,
            'site_url' => $this->account_type === 'external' && $this->account_site_url !== ''
                ? $this->account_site_url
                : null,
            'username' => $this->account_username !== '' ? $this->account_username : null,
            'notes' => $this->account_notes !== '' ? $this->account_notes : null,
        ]);

        $this->reset([
            'account_software_id',
            'account_site_name',
            'account_site_url',
            'account_username',
            'account_notes',
        ]);
        $this->account_type = 'software';

        $this->userware->load(['accounts.software']);

        Flux::modal('add-account')->close();
        Flux::toast(variant: 'success', text: __('Account added.'));
    }

    public function deleteAccount(UserwareAccount $account, DeleteUserwareAccount $deleteUserwareAccount): void
    {
        $this->authorize('delete', $account);
        abort_unless($account->userware_id === $this->userware->id, 404);

        $deleteUserwareAccount->handle($account);
        $this->userware->load(['accounts.software']);

        Flux::toast(variant: 'success', text: __('Account removed.'));
    }

    public function delete(DeleteUserware $deleteUserware): void
    {
        $this->authorize('delete', $this->userware);

        $deleteUserware->handle($this->userware);

        $this->redirect(route('assets.userware.index', absolute: false), navigate: true);
    }

    #[Computed]
    public function softwares()
    {
        return Software::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
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

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Assigned software') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Seat licenses assigned to this identity.') }}</flux:text>

        <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($userware->softwareAssignments as $assignment)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <a href="{{ route('assets.software.show', $assignment->software) }}" class="font-medium text-accent" wire:navigate>
                            {{ $assignment->software->name }}
                        </a>
                        <flux:text>
                            {{ $assignment->software->vendor ?? $assignment->software->license_type->label() }}
                            · {{ __('Assigned') }} {{ $assignment->assigned_at->format('M j, Y') }}
                        </flux:text>
                    </div>
                    <flux:button size="sm" :href="route('assets.software.show', $assignment->software)" wire:navigate>
                        {{ __('View') }}
                    </flux:button>
                </li>
            @empty
                <li class="py-3"><flux:text>{{ __('No software seats assigned.') }}</flux:text></li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Accounts') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Track services and logins for this identity.') }}</flux:text>
            </div>
            @can('create', [App\Models\UserwareAccount::class, $userware])
                <flux:modal.trigger name="add-account">
                    <flux:button size="sm" variant="primary" icon="plus">{{ __('Add account') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($userware->accounts as $account)
                <li class="flex items-start justify-between gap-3 py-3">
                    <div class="min-w-0">
                        @if ($account->isLinkedToSoftware())
                            <a href="{{ route('assets.software.show', $account->software) }}" class="font-medium text-accent" wire:navigate>
                                {{ $account->displayName() }}
                            </a>
                            <flux:text>{{ __('Linked software') }}</flux:text>
                        @else
                            <div class="font-medium">{{ $account->displayName() }}</div>
                            <flux:text>
                                <a href="{{ $account->site_url }}" target="_blank" rel="noopener noreferrer" class="text-accent">
                                    {{ $account->site_url }}
                                </a>
                            </flux:text>
                        @endif
                        @if ($account->username)
                            <flux:text>{{ __('Username') }}: {{ $account->username }}</flux:text>
                        @endif
                        @if ($account->notes)
                            <flux:text>{{ $account->notes }}</flux:text>
                        @endif
                    </div>
                    @can('delete', $account)
                        <flux:button size="sm" variant="danger" wire:click="deleteAccount({{ $account->id }})" wire:confirm="{{ __('Remove this account?') }}">
                            {{ __('Remove') }}
                        </flux:button>
                    @endcan
                </li>
            @empty
                <li class="py-3"><flux:text>{{ __('No accounts tracked yet.') }}</flux:text></li>
            @endforelse
        </ul>
    </div>

    <flux:modal name="add-account" class="max-w-lg">
        <form wire:submit="addAccount" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add account') }}</flux:heading>
                <flux:text>{{ __('Link software, or record an external site.') }}</flux:text>
            </div>

            <flux:radio.group wire:model.live="account_type" :label="__('Account type')">
                <flux:radio value="software" :label="__('Linked software')" />
                <flux:radio value="external" :label="__('External site')" />
            </flux:radio.group>

            @if ($account_type === 'software')
                <flux:select wire:model="account_software_id" :label="__('Software')" required>
                    <option value="">{{ __('Select software') }}</option>
                    @foreach ($this->softwares as $software)
                        <option value="{{ $software->id }}">{{ $software->name }}</option>
                    @endforeach
                </flux:select>
            @else
                <flux:input wire:model="account_site_name" :label="__('Site name')" required />
                <flux:input wire:model="account_site_url" type="url" :label="__('Site URL')" placeholder="https://..." required />
            @endif

            <flux:input wire:model="account_username" :label="__('Username')" />
            <flux:textarea wire:model="account_notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Add account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
