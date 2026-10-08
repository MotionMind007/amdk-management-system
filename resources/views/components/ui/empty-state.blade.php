@props(['title', 'description'])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center']) }}>
    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-ui.icon name="inbox" class="size-5" /></span>
    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @if (trim($slot))<div class="mt-5">{{ $slot }}</div>@endif
</div>
