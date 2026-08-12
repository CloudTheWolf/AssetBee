@php
    /** @var string $url */
    /** @var string $name */
    /** @var string $appName */
@endphp

<x-mail::message>
# {{ __('Welcome to :app!', ['app' => $appName]) }}

{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Thanks for creating your account. Please verify your email address so we know it\'s really you.') }}

<x-mail::button :url="$url">
{{ __('Verify email address') }}
</x-mail::button>

{{ __('If you did not create an account, no further action is required.') }}
</x-mail::message>
