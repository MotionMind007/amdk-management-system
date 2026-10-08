@props(['tone' => 'gray'])

@php
    $classes = match ($tone) {
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>{{ $slot }}</span>
