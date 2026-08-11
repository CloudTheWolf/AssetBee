<?php

use App\Models\OrganizationInvitation;

/** @var \App\Models\OrganizationInvitation|null $invitation */
$invitation = $invitation ?? null;
?>

<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-7">
        <x-auth-header
            :title="__('Create your account')"
            :description="$invitation
                ? __('Join :organization with the invited email address.', ['organization' => $invitation->organization->name])
                : __('Set up access and start tracking assets across your organization.')"
        />

        <x-auth-session-status :status="session('status')" />

        <x-google-auth-button :label="__('Sign up with Google')" />

        <div class="auth-divider">
            <span>{{ __('or email') }}</span>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
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

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>
        </form>

        <div class="text-center text-sm text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
