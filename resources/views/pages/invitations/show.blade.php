<?php

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Models\OrganizationInvitation;
use App\Support\Registration;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Organization invitation')] class extends Component {
    #[Locked]
    public string $token = '';

    public ?OrganizationInvitation $invitation = null;

    public string $error = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $invitation = OrganizationInvitation::query()
            ->with(['organization', 'inviter'])
            ->where('token', $token)
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            $this->error = __('This invitation is invalid or has expired.');

            return;
        }

        $this->invitation = $invitation;
        Registration::rememberInvitation($invitation);

        if (Auth::check() && strtolower(Auth::user()->email) === strtolower($invitation->email)) {
            app(AcceptOrganizationInvitation::class)->handle($invitation, Auth::user());

            $this->redirect(route('dashboard', absolute: false));
        }
    }

    public function accept(AcceptOrganizationInvitation $acceptOrganizationInvitation): void
    {
        if ($this->invitation === null) {
            return;
        }

        if (! Auth::check()) {
            $this->redirect(route('register', absolute: false));

            return;
        }

        $acceptOrganizationInvitation->handle($this->invitation, Auth::user());

        Flux::toast(variant: 'success', text: __('You joined :organization.', [
            'organization' => $this->invitation->organization->name,
        ]));

        $this->redirect(route('dashboard', absolute: false));
    }
}; ?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6 py-10">
    @if ($error !== '')
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Invitation unavailable') }}</flux:heading>
            <flux:text class="mt-2">{{ $error }}</flux:text>
            <div class="mt-6">
                <flux:button :href="route('login')">{{ __('Back to login') }}</flux:button>
            </div>
        </div>
    @elseif ($invitation)
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Join :organization', ['organization' => $invitation->organization->name]) }}</flux:heading>
            <flux:text class="mt-2">
                {{ __(':inviter invited :email to join as :role.', [
                    'inviter' => $invitation->inviter->name,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                ]) }}
            </flux:text>

            <div class="mt-6 flex flex-col gap-3">
                @guest
                    <flux:button variant="primary" :href="route('register')">
                        {{ __('Create account to accept') }}
                    </flux:button>
                    <flux:button :href="route('login')">
                        {{ __('Log in to accept') }}
                    </flux:button>
                    <x-google-auth-button :label="__('Continue with Google')" />
                @else
                    @if (strtolower(auth()->user()->email) === strtolower($invitation->email))
                        <flux:button variant="primary" wire:click="accept">
                            {{ __('Accept invitation') }}
                        </flux:button>
                    @else
                        <flux:text>
                            {{ __('You are signed in as :email. Sign in as :invited to accept.', [
                                'email' => auth()->user()->email,
                                'invited' => $invitation->email,
                            ]) }}
                        </flux:text>
                    @endif
                @endguest
            </div>
        </div>
    @endif
</div>
