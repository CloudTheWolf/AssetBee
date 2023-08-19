@props(['active'])

@php
$classes = ($active ?? false)
            ? 'active block px-4 py-2 hover:border-amber-300 dark:hover:border-amber-300 dark:text-gray-400 dark:hover:text-white transition duration-150 ease-in-out'
            : 'block px-4 py-2 hover:border-amber-300 dark:hover:border-amber-300 dark:text-gray-400 dark:hover:text-white transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
