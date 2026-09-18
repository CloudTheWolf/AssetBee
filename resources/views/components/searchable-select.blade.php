@props([
    'options' => [],
    'placeholder' => __('All assignees'),
    'searchPlaceholder' => __('Search...'),
    'emptyText' => __('No results found'),
])

@php
    $normalizedOptions = collect($options)
        ->map(function (mixed $option): array {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                    'keywords' => (string) ($option['keywords'] ?? ($option['label'] ?? '')),
                ];
            }

            return [
                'value' => (string) data_get($option, 'value', data_get($option, 'id', '')),
                'label' => (string) data_get($option, 'label', data_get($option, 'name', '')),
                'keywords' => (string) data_get($option, 'keywords', data_get($option, 'email', data_get($option, 'name', ''))),
            ];
        })
        ->values()
        ->all();

    $wireModel = $attributes->wire('model');
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative']) }}
    x-data="{
        open: false,
        search: '',
        value: @entangle($wireModel),
        options: {{ \Illuminate\Support\Js::from($normalizedOptions) }},
        placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
        get selectedOption() {
            return this.options.find((option) => String(option.value) === String(this.value)) ?? null;
        },
        get selectedLabel() {
            return this.selectedOption?.label ?? this.placeholder;
        },
        get filteredOptions() {
            const query = this.search.trim().toLowerCase();

            if (query === '') {
                return this.options;
            }

            return this.options.filter((option) => {
                return option.label.toLowerCase().includes(query)
                    || option.keywords.toLowerCase().includes(query);
            });
        },
        select(optionValue) {
            this.value = optionValue;
            this.open = false;
            this.search = '';
        },
        toggle() {
            this.open = ! this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() {
            this.open = false;
            this.search = '';
        },
    }"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
    data-test="searchable-select"
>
    <button
        type="button"
        class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-sm text-zinc-800 shadow-xs hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600"
        x-on:click="toggle()"
        x-bind:aria-expanded="open.toString()"
        data-test="searchable-select-trigger"
    >
        <span class="truncate" x-text="selectedLabel" x-bind:class="{ 'text-zinc-500 dark:text-zinc-400': ! selectedOption }"></span>
        <flux:icon.chevron-down class="size-4 shrink-0 text-zinc-400" />
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition
        class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
        data-test="searchable-select-dropdown"
    >
        <div class="border-b border-zinc-200 p-2 dark:border-zinc-600">
            <input
                x-ref="search"
                type="search"
                x-model="search"
                placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-800 outline-none focus:border-accent dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                data-test="searchable-select-search"
            />
        </div>

        <ul class="max-h-60 overflow-y-auto py-1">
            <template x-for="option in filteredOptions" :key="option.value === '' ? '__empty' : option.value">
                <li>
                    <button
                        type="button"
                        class="flex w-full items-center px-3 py-2 text-left text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-600"
                        x-bind:class="{ 'bg-zinc-100 font-medium dark:bg-zinc-600': String(option.value) === String(value) }"
                        x-on:click="select(option.value)"
                        x-text="option.label"
                    ></button>
                </li>
            </template>

            <li x-show="filteredOptions.length === 0" class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $emptyText }}
            </li>
        </ul>
    </div>
</div>
