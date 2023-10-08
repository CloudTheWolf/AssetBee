<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manage Hardware') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-3  gap-4">
                <div class="bg-white col-span-2 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <livewire:assets.hardware-table />
                </div>
                <div class="bg-white col-span-1 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <livewire:assets.add-new-hardware-form />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
