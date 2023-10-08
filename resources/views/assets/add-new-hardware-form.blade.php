<div class="mt-5 md:mt-0 md:col-span-2">
    <form wire:submit="$submit">
        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="form-group flex items-center justify-center px-4 py-3 bg-gray-50 dark:bg-gray-800 text-center sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">
                <div class="relative">
                    <x-label for="assetName">Asset Name</x-label>
                    <x-input id="assetName" type="text" placeholder="Johns-Laptop"/>
                </div>
            </div>
            <div class="flex items-center justify-center px-4 py-3 bg-gray-50 dark:bg-gray-800 text-center sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">
                <livewire:assets.hadrware-vendor-search />
            </div>
            <div class="form-group flex items-center justify-center px-4 py-3 bg-gray-50 dark:bg-gray-800 text-center sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">
                <div class="relative">
                    <x-label for="assetName">Custom Asset Number</x-label>
                    <x-input id="assetName" type="text" placeholder="COMP20230123"/>
                </div>
            </div>
            <div class="form-group flex items-center justify-center px-4 py-3 bg-gray-50 dark:bg-gray-800 text-center sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">
                <div class="relative">
                    <x-primary-button type="button">Add Asset</x-primary-button>
                </div>
            </div>
        </div>
    </form>
</div>
