<x-app-layout title="Pengiriman Barang" module="inventory" :module-data="config('modules.inventory')">
    <div>
        <p class="text-sm font-semibold text-brand-700">Gudang</p>
        <h1 class="mt-1 text-2xl font-semibold">Pengiriman & Surat Jalan</h1>
        <p class="mt-2 text-sm text-slate-500">Stok produk jadi baru berkurang ketika surat jalan diposting.</p>
    </div>

    <section class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Menunggu Pengiriman</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Faktur</th><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3">Gudang</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-5 py-4"><p class="font-semibold">{{ $sale->number }}</p><p class="text-xs text-slate-500">{{ $sale->sale_date->format('d/m/Y') }}</p></td>
                            <td class="px-5 py-4">{{ $sale->customer->name }}</td>
                            <td class="px-5 py-4">{{ $sale->warehouse->name }}</td>
                            <td class="px-5 py-4"><x-ui.badge :tone="$sale->delivery_status === 'partial' ? 'orange' : 'blue'">{{ $sale->delivery_status === 'partial' ? 'Terkirim Sebagian' : 'Menunggu' }}</x-ui.badge></td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('sales.deliveries.create', $sale) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand-600 px-3 text-sm font-semibold text-white">Proses Pengiriman</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Tidak ada pengiriman tertunda" description="Semua penjualan terposting sudah selesai dikirim." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-5">{{ $sales->links() }}</div>

    <section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Surat Jalan Terbaru</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($deliveryNotes as $deliveryNote)
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="font-semibold">{{ $deliveryNote->number }} · {{ $deliveryNote->sale->number }}</p><p class="mt-1 text-xs text-slate-500">{{ $deliveryNote->sale->customer->name }} · {{ $deliveryNote->delivery_date->format('d/m/Y') }}</p></div>
                    <a href="{{ route('sales.deliveries.print', $deliveryNote) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700">Preview Surat Jalan</a>
                </div>
            @empty
                <p class="p-5 text-sm text-slate-500">Belum ada surat jalan.</p>
            @endforelse
        </div>
    </section>
</x-app-layout>
