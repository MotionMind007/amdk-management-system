@props(['label', 'value', 'tone' => 'blue'])

@php
    $dot = match ($tone) {
        'red' => 'bg-red-500',
        'orange' => 'bg-orange-500',
        'green' => 'bg-emerald-500',
        default => 'bg-blue-500',
    };
@endphp

<article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-center gap-2 text-sm font-medium text-slate-500"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}</div>
    <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
</article>
