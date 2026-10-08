<x-app-layout title="Ringkasan Perusahaan">
    <div class="mx-auto max-w-6xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-brand-700">Ringkasan Perusahaan</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Dashboard</h1>
                <p class="mt-2 text-sm text-slate-500">Informasi yang perlu diperhatikan hari ini.</p>
            </div>
            <a href="{{ route('modules.index') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-950">Pilih modul</a>
        </div>

        <section class="mt-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :tone="$stat['tone']" />
            @endforeach
        </section>

        <div class="mt-7 grid gap-6 lg:grid-cols-2">
            <section>
                <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-900">Penjualan terbaru</h2><a href="{{ route('sales.index') }}" class="text-sm font-medium text-brand-700">Lihat modul</a></div>
                @if ($latestSales->isEmpty())
                    <x-ui.empty-state title="Belum ada transaksi penjualan" description="Transaksi terbaru akan tampil di sini setelah penjualan pertama dibuat." />
                @else
                    <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm">@foreach ($latestSales as $sale)<div class="flex items-center justify-between gap-4 p-4"><div><p class="text-sm font-semibold">{{ $sale->number }}</p><p class="text-xs text-slate-500">{{ $sale->customer->name }}</p></div><div class="text-right"><p class="text-sm font-semibold">Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</p><x-ui.badge :tone="$sale->status === 'paid' ? 'green' : 'orange'">{{ str($sale->status)->replace('_', ' ')->title() }}</x-ui.badge></div></div>@endforeach</div>
                @endif
            </section>
            <section>
                <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-900">Peringatan stok</h2><a href="{{ route('inventory.index') }}" class="text-sm font-medium text-brand-700">Lihat stok</a></div>
                <x-ui.empty-state title="Belum ada peringatan stok" description="Produk di bawah batas minimum akan tampil di sini." />
            </section>
        </div>
    </div>
</x-app-layout>
