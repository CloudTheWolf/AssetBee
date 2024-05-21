<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manage Userware') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-5  gap-4">
                <div class="bg-white col-span-5 dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="relative overflow-hidden bg-white shadow-md dark:bg-gray-800 sm:rounded-lg">
                        <div class="flex-row items-center justify-between p-4 space-y-3 sm:flex sm:space-y-0 sm:space-x-4 mb-4">
                            <div>
                                <h5 class="mr-3 font-semibold dark:text-white">{{__('Userware')}}</h5>
                                <p class="text-gray-500 dark:text-gray-400">Manage all your end users (Aka Asset Owners)!</p>
                            </div>
                                <livewire:assets.userware.csv-import />
                        </div>
                        <div class="ml-2 mr-2 mb-4">
                            <livewire:assets.userware.userware-table />
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-app-layout>
