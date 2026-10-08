<x-app-layout title="Pilih Modul">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-brand-700">Pusat Modul</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Selamat datang, {{ auth()->user()->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">Pilih bagian pekerjaan yang ingin Anda buka.</p>
            </div>
            @if (auth()->user()->hasPermission('dashboard.view'))
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Lihat ringkasan perusahaan</a>
            @endif
        </div>

        <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Daftar modul">
            @forelse ($modules as $key => $module)
                <a href="{{ isset($module['route']) ? route($module['route']) : route('modules.show', $key) }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-100">
                        <x-ui.icon :name="$module['icon']" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-base font-semibold text-slate-900">{{ $module['label'] }}</h2>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">{{ $module['description'] }}</p>
                    <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">Buka modul <span aria-hidden="true">→</span></span>
                </a>
            @empty
                <div class="sm:col-span-2 xl:col-span-3">
                    <x-ui.empty-state title="Belum ada modul yang dapat diakses" description="Hubungi administrator untuk mengatur hak akses akun Anda." />
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
