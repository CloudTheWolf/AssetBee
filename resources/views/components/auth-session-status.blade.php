@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-brand-cyan/30 bg-brand-cyan/10 px-3 py-2 text-sm font-medium text-brand-cyan-soft']) }}>
        {{ $status }}
    </div>
@endif
