<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manage Userware') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-5  gap-4">
                <div class="bg-white col-span-4 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <livewire:assets.userware.userware-table />
                </div>
                <div class="bg-white col-span-1 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <livewire:assets.userware.csv-import />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
