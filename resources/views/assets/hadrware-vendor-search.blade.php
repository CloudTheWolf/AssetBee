<div>
    <div class="relative">
        <x-input type="text" id="vendor" wire:model="search" wire:keyup="searchResult" placeholder="Lenovo" />
        <!-- Search result list -->
            <ul >
                @if(!empty($records))

                    @foreach($records as $record)

                        <li wire:click="fetchVendorDetail({{ $record->id }})">{{ $record->name}}</li>

                    @endforeach
                @endif
            </ul>

    </div>
</div>
