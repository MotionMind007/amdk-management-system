@props(['type' => 'button', 'variant' => 'primary'])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        default => 'bg-brand-600 text-white hover:bg-brand-700',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition {$classes}"]) }}>
    {{ $slot }}
</button>
