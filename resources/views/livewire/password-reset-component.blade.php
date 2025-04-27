<div>
    <form>
        @csrf

        <div class="block">
            <label for="email" value="{{ __('Email') }}" />
            <input id="email" wire:model.live="email" type="email" placeholder="Email" autofocus autocomplete="username" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full" />
            @error('email') <span class="text-red-500">{{ $message }}</span>@enderror
        </div>
        @if (session()->has('message'))
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif
        <div class="flex items-center justify-end mt-4">
            <button wire:click.prevent="Login" class="ml-4 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-transparent border border-yellow-500 rounded-md font-semibold text-xs text-white dark:text-yellow-500 uppercase tracking-widest hover:bg-yellow-300 dark:hover:bg-yellow-300 focus:bg-yellow-300 dark:focus:bg-yellow-500 active:bg-yellow-300 dark:active:bg-transparent dark:active:text-white dark:hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-yellow-800 transition ease-in-out duration-150">
                {{ __('Login') }}
            </button>
            <button wire:click.prevent="ResetPassword" class="ml-4 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-yellow-400 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-900 uppercase tracking-widest hover:bg-yellow-300 dark:hover:bg-yellow-300 focus:bg-yellow-300 dark:focus:bg-yellow-500 active:bg-yellow-300 dark:active:bg-yellow-300 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-yellow-800 transition ease-in-out duration-150">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
</div>
