<x-app-layout title="Detail Stok Opname" module="inventory" :module-data="config('modules.inventory')">
    @php($differenceCount = $stockOpname->items->filter(fn ($item) => $item->difference() !== 0.0)->count())

    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('inventory.opnames.index') }}" class="text-sm font-semibold text-brand-700">&larr; Kembali</a>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight">{{ $stockOpname->number }}</h1>
            <p class="mt-2 text-sm text-slate-500">Periode {{ $stockOpname->period }} · {{ $stockOpname->warehouse->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($stockOpname->status === 'draft' && auth()->user()->hasPermission('inventory.manage'))
                <a href="{{ route('inventory.opnames.edit', $stockOpname) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Edit Draft</a>
            @endif
            @if ($stockOpname->status === 'draft' && auth()->user()->hasPermission('system.manage'))
                <form method="POST" action="{{ route('inventory.opnames.post', $stockOpname) }}" onsubmit="return confirm('Posting stok opname? Selisih akan langsung menyesuaikan saldo stok dan data tidak dapat diedit lagi.')">
                    @csrf
                    <x-ui.button type="submit">Posting Stok Opname</x-ui.button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p><div class="mt-3"><x-ui.badge :tone="$stockOpname->status === 'posted' ? 'green' : 'gray'">{{ $stockOpname->status === 'posted' ? 'Terposting' : 'Draft' }}</x-ui.badge></div></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Hitung</p><p class="mt-3 font-semibold text-slate-800">{{ $stockOpname->opname_date->format('d/m/Y') }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Barang Dihitung</p><p class="mt-3 font-semibold text-slate-800">{{ $stockOpname->items->count() }} barang</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Barang Berselisih</p><p class="mt-3 font-semibold {{ $differenceCount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $differenceCount }} barang</p></div>
    </div>

    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[800px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Produk / Bahan</th><th class="px-5 py-3 text-right">Stok Sistem</th><th class="px-5 py-3 text-right">Stok Fisik</th><th class="px-5 py-3 text-right">Selisih</th><th class="px-5 py-3">Keterangan</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($stockOpname->items as $item)
                        @php($difference = $item->difference())
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-slate-800">{{ $item->product->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">{{ number_format((float) $item->system_quantity, 3, ',', '.') }} {{ $item->product->unit->code }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-800">{{ number_format((float) $item->physical_quantity, 3, ',', '.') }} {{ $item->product->unit->code }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold {{ $difference === 0.0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $difference > 0 ? '+' : '' }}{{ number_format($difference, 3, ',', '.') }} {{ $item->product->unit->code }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $item->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Dibuat Oleh</p><p class="mt-2 text-slate-700">{{ $stockOpname->creator->name }}</p></div>
        <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Diposting Oleh</p><p class="mt-2 text-slate-700">{{ $stockOpname->poster?->name ?? 'Belum diposting' }}</p></div>
        <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Catatan</p><p class="mt-2 text-slate-700">{{ $stockOpname->notes ?: 'Tidak ada catatan.' }}</p></div>
    </div>
</x-app-layout>
