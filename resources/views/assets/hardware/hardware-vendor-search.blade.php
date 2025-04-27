<div class="relative">
    <x-label for="vendor">Brand</x-label>
    <x-input type="text" id="vendor" wire:model="search" wire:keyup="searchResult" placeholder="Lenovo" />
    <!-- Search result list -->
    @if($showResult)
        @if(!empty($records))
            <ul class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
            @foreach($records as $record)

                <li wire:click="fetchVendorDetail({{ $record->id }})">{{ $record->name}}</li>

            @endforeach
            </ul>
        @endif
    @endif
</div>

