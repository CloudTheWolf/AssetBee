<?php

use App\Actions\Organizations\InviteOrganizationMember;
use App\Actions\Organizations\RemoveOrganizationMember;
use App\Actions\Organizations\RevokeOrganizationInvitation;
use App\Actions\Organizations\UpdateOrganization;
use App\Actions\Organizations\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organization settings')] class extends Component {
    use AuthorizesRequests;

    public string $name = '';

    public string $google_hosted_domains = '';

    public string $invite_email = '';

    public string $invite_role = 'member';

    public function mount(): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('manage', $organization);

        $this->name = $organization->name;
        $this->google_hosted_domains = $organization->googleDomains()->pluck('domain')->implode("\n");
    }

    public function save(UpdateOrganization $updateOrganization): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('update', $organization);

        $updateOrganization->handle($organization, [
            'name' => $this->name,
            'google_hosted_domains' => $this->google_hosted_domains,
        ]);

        Flux::toast(variant: 'success', text: __('Organization updated.'));
    }

    public function invite(InviteOrganizationMember $inviteOrganizationMember): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('invite', $organization);

        $inviteOrganizationMember->handle($organization, auth()->user(), [
            'email' => $this->invite_email,
            'role' => $this->invite_role,
        ]);

        $this->reset(['invite_email']);
        $this->invite_role = OrganizationRole::Member->value;

        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function revokeInvitation(OrganizationInvitation $invitation, RevokeOrganizationInvitation $revokeOrganizationInvitation): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('invite', $organization);
        abort_unless($invitation->organization_id === $organization->id, 404);

        $revokeOrganizationInvitation->handle($invitation);

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    public function updateMemberRole(int $userId, string $role, UpdateOrganizationMemberRole $updateOrganizationMemberRole): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('manage', $organization);

        $member = User::query()->findOrFail($userId);
        $updateOrganizationMemberRole->handle($organization, $member, OrganizationRole::from($role));

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    public function removeMember(int $userId, RemoveOrganizationMember $removeOrganizationMember): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('manage', $organization);

        $member = User::query()->findOrFail($userId);
        $removeOrganizationMember->handle($organization, $member);

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }

    #[Computed]
    public function organization(): Organization
    {
        return CurrentOrganization::require()->load(['googleDomains']);
    }

    #[Computed]
    public function members()
    {
        return $this->organization->users()->orderBy('name')->get();
    }

    #[Computed]
    public function invitations()
    {
        return $this->organization->invitations()->pending()->with('inviter')->latest()->get();
    }

    #[Computed]
    public function canInvite(): bool
    {
        return auth()->user()->can('invite', $this->organization);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Organization') }}</flux:heading>
        <flux:text>{{ __('Manage settings, members, and invitations for :name.', ['name' => $this->organization->name]) }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Settings') }}</flux:heading>
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:textarea
            wire:model="google_hosted_domains"
            :label="__('Google Workspace domains')"
            :description="__('Comma or newline separated. Users signing in from these domains can join automatically when allowed.')"
            rows="3"
        />
        <div class="flex justify-end">
            <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Members') }}</flux:heading>
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->members as $member)
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between" wire:key="member-{{ $member->id }}">
                    <div>
                        <div class="font-medium">{{ $member->name }}</div>
                        <flux:text>{{ $member->email }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($member->pivot->role === OrganizationRole::Owner->value)
                            <flux:badge>{{ __('Owner') }}</flux:badge>
                        @else
                            <flux:select
                                x-on:change="$wire.updateMemberRole({{ $member->id }}, $event.target.value)"
                                class="w-36"
                            >
                                <option value="admin" @selected($member->pivot->role === 'admin')>{{ __('Admin') }}</option>
                                <option value="member" @selected($member->pivot->role === 'member')>{{ __('Member') }}</option>
                            </flux:select>
                            <flux:button size="sm" variant="danger" wire:click="removeMember({{ $member->id }})" wire:confirm="{{ __('Remove this member?') }}">
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($this->canInvite)
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Invitations') }}</flux:heading>

            <form wire:submit="invite" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:input wire:model="invite_email" type="email" :label="__('Email')" class="flex-1" required />
                <flux:select wire:model="invite_role" :label="__('Role')" class="sm:w-40">
                    <option value="admin">{{ __('Admin') }}</option>
                    <option value="member">{{ __('Member') }}</option>
                </flux:select>
                <flux:button variant="primary" type="submit">{{ __('Send invite') }}</flux:button>
            </form>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->invitations as $invitation)
                    <li class="flex items-center justify-between py-3" wire:key="invite-{{ $invitation->id }}">
                        <div>
                            <div class="font-medium">{{ $invitation->email }}</div>
                            <flux:text>{{ $invitation->role->label() }} · {{ __('Expires :date', ['date' => $invitation->expires_at->toFormattedDateString()]) }}</flux:text>
                        </div>
                        <flux:button size="sm" variant="danger" wire:click="revokeInvitation({{ $invitation->id }})" wire:confirm="{{ __('Revoke this invitation?') }}">
                            {{ __('Revoke') }}
                        </flux:button>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No pending invitations.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
