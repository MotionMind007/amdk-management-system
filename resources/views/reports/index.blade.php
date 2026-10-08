<x-app-layout title="Laporan" module="reports" :module-data="config('modules.reports')">
    <div>
        <p class="text-sm font-semibold text-brand-700">Laporan</p>
        <h1 class="mt-1 text-2xl font-semibold">Ringkasan Operasional</h1>
        <p class="mt-2 text-sm text-slate-500">Angka sederhana untuk memantau operasi perusahaan.</p>
    </div>

    <form method="GET" class="mt-7 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <x-ui.input label="Tanggal Mulai" name="start_date" type="date" :value="$startDate" required />
        <x-ui.input label="Tanggal Akhir" name="end_date" type="date" :value="$endDate" required />
        <div class="flex items-end">
            <x-ui.button type="submit" variant="secondary" class="w-full">Tampilkan</x-ui.button>
        </div>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat-card label="Penjualan" :value="'Rp '.number_format((float) $salesTotal, 0, ',', '.')" tone="blue" />
        <x-ui.stat-card label="Pembelian diterima" :value="'Rp '.number_format((float) $purchaseTotal, 0, ',', '.')" tone="orange" />
        <x-ui.stat-card label="Hasil produksi" :value="number_format((float) $productionTotal, 0, ',', '.').' karton'" tone="green" />
        <x-ui.stat-card label="Uang masuk" :value="'Rp '.number_format((float) $cashIn, 0, ',', '.')" tone="green" />
        <x-ui.stat-card label="Uang keluar" :value="'Rp '.number_format((float) $cashOut, 0, ',', '.')" tone="red" />
        <x-ui.stat-card label="Arus kas bersih" :value="'Rp '.number_format((float) $cashIn - (float) $cashOut, 0, ',', '.')" tone="blue" />
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Total piutang berjalan" :value="'Rp '.number_format((float) $receivableTotal, 0, ',', '.')" tone="orange" />
        <x-ui.stat-card label="Total hutang berjalan" :value="'Rp '.number_format((float) $payableTotal, 0, ',', '.')" tone="orange" />
    </section>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Penjualan per Produk</h2>
            <p class="mt-1 text-sm text-slate-500">Jumlah produk terjual dari transaksi yang sudah diposting pada periode {{ date('d/m/Y', strtotime($startDate)) }}–{{ date('d/m/Y', strtotime($endDate)) }}.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Nama Produk</th>
                        <th class="px-5 py-3 text-right">Jumlah Terjual</th>
                        <th class="px-5 py-3 text-right">Nilai Penjualan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($productSales as $productSale)
                        <tr>
                            <td class="px-5 py-4 font-medium text-slate-900">{{ $productSale->name }}</td>
                            <td class="px-5 py-4 text-right">
                                {{ rtrim(rtrim(number_format((float) $productSale->quantity_sold, 3, ',', '.'), '0'), ',') }} {{ $productSale->unit_code }}
                            </td>
                            <td class="px-5 py-4 text-right">Rp {{ number_format((float) $productSale->sales_amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-6">
                                <x-ui.empty-state title="Belum ada produk terjual" description="Tidak ada penjualan yang sudah diposting pada periode ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($productSales->isNotEmpty())
                    <tfoot class="bg-slate-50 font-semibold text-slate-900">
                        <tr>
                            <td class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right">{{ number_format((float) $productSales->sum('quantity_sold'), 0, ',', '.') }} karton</td>
                            <td class="px-5 py-3 text-right">Rp {{ number_format((float) $productSales->sum('sales_amount'), 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <p class="mt-6 rounded-xl bg-slate-100 p-4 text-sm text-slate-600">Ringkasan ini bukan laporan laba rugi formal. Nilai menampilkan transaksi penjualan, penerimaan pembelian, kas, piutang, dan hutang.</p>
</x-app-layout>
