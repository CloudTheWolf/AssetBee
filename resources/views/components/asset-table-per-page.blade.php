<flux:select
    wire:model.live.number="perPage"
    :aria-label="__('Rows per page')"
    data-test="rows-per-page"
    {{ $attributes->merge(['class' => 'sm:w-40']) }}
>
    @foreach (\App\Support\AssetTablePagination::PER_PAGE_OPTIONS as $option)
        <option value="{{ $option }}">{{ __(':count per page', ['count' => $option]) }}</option>
    @endforeach
</flux:select>
