<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Welcome back')"
            :description="__('Sign in to continue managing your organization assets.')"
        />

        <x-auth-session-status :status="session('status')" />

        <div class="flex flex-col gap-2.5">
            <x-google-auth-button :label="__('Continue with Google')" />
            <x-passkey-verify :separator="null" />
        </div>

        <div class="auth-divider">
            <span>{{ __('or email') }}</span>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 end-0 text-sm" :href="route('password.request')">
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="mt-1 w-full" data-test="login-button">
                {{ __('Log in') }}
            </flux:button>
        </form>

        <div class="pt-1 text-center text-sm text-zinc-500">
            @if (\App\Support\Registration::isOpen(\App\Support\Registration::pendingInvitation()))
                <span>{{ __("Don't have an account?") }}</span>
                <flux:link :href="route('register')">{{ __('Sign up') }}</flux:link>
            @else
                <span>{{ __('Need access? Ask an organization owner for an invite.') }}</span>
            @endif
        </div>
    </div>
</x-layouts::auth>
