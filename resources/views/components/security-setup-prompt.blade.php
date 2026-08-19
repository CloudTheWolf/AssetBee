<?php

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;
use Livewire\Component;

new class extends Component
{
    public const SESSION_KEY = 'security_setup_prompt_dismissed';

    public bool $show = false;

    public function mount(): void
    {
        if (config('app.demo_mode')) {
            return;
        }

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if (! Features::canManageTwoFactorAuthentication() && ! Features::canManagePasskeys()) {
            return;
        }

        if (session()->has(self::SESSION_KEY)) {
            return;
        }

        if ($user->hasConfiguredSecurity()) {
            return;
        }

        // Finish organization onboarding before prompting for 2FA / passkeys.
        if ($user->organizations()->doesntExist()) {
            return;
        }

        if (request()->routeIs('security.edit', 'password.confirm', 'two-factor.*', 'organizations.create')) {
            return;
        }

        $this->show = true;
    }

    public function dismiss(): void
    {
        session()->put(self::SESSION_KEY, true);
        $this->show = false;
    }
}; ?>

<div>
    @if ($show)
        <flux:modal wire:model.self="show" class="max-w-lg" @close="dismiss">
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Secure your account') }}</flux:heading>
                    <flux:text>
                        {{ __('Add two-factor authentication or a security key so only you can sign in to your account.') }}
                    </flux:text>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <flux:button variant="ghost" wire:click="dismiss" data-test="dismiss-security-setup">
                        {{ __('Remind me later') }}
                    </flux:button>
                    <flux:button
                        variant="primary"
                        :href="route('security.edit')"
                        wire:click="dismiss"
                        wire:navigate
                        data-test="setup-security"
                    >
                        {{ __('Set up security') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
