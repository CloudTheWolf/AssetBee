<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manage Hardware') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-5  gap-4">
                <div class="bg-white col-span-4 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="relative overflow-hidden bg-white shadow-md dark:bg-gray-800 sm:rounded-lg">
                        <div class="flex-row items-center justify-between p-4 space-y-3 sm:flex sm:space-y-0 sm:space-x-4">
                            <div>
                                <h5 class="mr-3 font-semibold dark:text-white">Hardware</h5>
                                <p class="text-gray-500 dark:text-gray-400">Manage all your existing hardware assets in one place!</p>
                            </div>
                <button type="button" data-modal-target="add-hardware-modal" data-modal-toggle="add-hardware-modal"
                        class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-900 rounded-lg bg-yellow-500 hover:bg-yellow-400 focus:ring-4 focus:ring-yellow-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-2 -ml-1" viewBox="0 0 20 20" fill="currentColor"
                         aria-hidden="true">
                        <path
                            d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                    </svg>
                    Add Hardware
                </button>
                        </div>
                        <div class="ml-2 mr-2">
                            <livewire:assets.hardware.hardware-table />
                        </div>
                    </div>

                </div>
                <div id="add-hardware-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
                        <livewire:assets.hardware.add-new-hardware-form />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
