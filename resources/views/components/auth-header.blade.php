@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">
        {{ $title }}
    </h1>
    <p class="text-sm leading-relaxed text-zinc-400 sm:text-base">
        {{ $description }}
    </p>
</div>
