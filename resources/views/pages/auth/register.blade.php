<?php

use App\Models\OrganizationInvitation;

/** @var \App\Models\OrganizationInvitation|null $invitation */
$invitation = $invitation ?? null;
?>

<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Create an account')"
            :description="$invitation
                ? __('Join :organization with the invited email address.', ['organization' => $invitation->organization->name])
                : __('Enter your details below to create your account')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-google-auth-button :label="__('Sign up with Google')" />

        <div class="relative flex items-center gap-4 py-1">
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
            <span class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('or') }}</span>
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email', $invitation?->email)"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
                :readonly="(bool) $invitation"
            />

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
