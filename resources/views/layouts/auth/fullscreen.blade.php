<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-screen relative min-h-dvh overflow-x-hidden antialiased">
        <div
            class="auth-splash"
            style="background-image: url('{{ asset('img/splash.png') }}')"
            aria-hidden="true"
        ></div>
        <div class="auth-splash-shade" aria-hidden="true"></div>

        <div class="relative z-10 grid min-h-dvh lg:grid-cols-[1.2fr_minmax(22rem,30rem)]">
            <section class="auth-brand relative flex min-h-[42vh] flex-col justify-center px-6 py-10 sm:px-10 lg:min-h-dvh lg:px-16 xl:px-20">
                <div class="flex max-w-xl flex-col gap-8">
                    <a href="{{ route('home') }}" class="inline-flex w-fit">
                        <img
                            src="{{ asset('img/logo.png') }}"
                            alt="{{ config('app.name') }}"
                            class="h-16 w-auto object-contain sm:h-20 lg:h-24"
                        >
                    </a>

                    <div class="space-y-3">
                        <p class="font-display text-3xl leading-[1.15] font-semibold tracking-tight text-white sm:text-4xl lg:text-5xl">
                            {{ __('Keep every asset accounted for.') }}
                        </p>
                        <p class="max-w-md text-base leading-relaxed text-zinc-300">
                            {{ __('Hardware, software, cloud, and the people who use them — in one place.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="auth-panel relative flex items-center px-6 py-8 sm:px-10 lg:px-12 xl:px-14">
                <div class="auth-form-shell mx-auto w-full max-w-md lg:mx-0">
                    {{ $slot }}
                </div>
            </section>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
