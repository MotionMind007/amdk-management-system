<x-app-layout :title="$moduleData['label']" :module="$module" :module-data="$moduleData">
    <div class="max-w-5xl">
        <div>
            <p class="text-sm font-semibold text-brand-700">Modul</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $moduleData['label'] }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $moduleData['description'] }}</p>
        </div>

        <section class="mt-7 grid gap-4 sm:grid-cols-2">
            @foreach ($moduleData['navigation'] as $index => $item)
                @continue(isset($item['permission']) && ! auth()->user()->hasPermission($item['permission']))
                <a href="{{ route($item['route']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $item['label'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">{{ $index === 0 ? 'Lihat ringkasan informasi penting modul.' : 'Kelola data dengan alur yang ringkas dan jelas.' }}</p>
                        </div>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-ui.icon :name="$moduleData['icon']" class="size-5" /></span>
                    </div>
                </a>
            @endforeach
        </section>
    </div>
</x-app-layout>
