<x-app-layout title="Laporan Laba Rugi" module="reports" :module-data="config('modules.reports')">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Laporan</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">Laba Rugi Sederhana</h1>
            <p class="mt-2 text-sm text-slate-500">Ringkasan pendapatan dan beban operasional perusahaan berdasarkan periode.</p>
        </div>
        <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 print:hidden">
            Cetak Laporan
        </button>
    </div>

    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Laporan sementara sebelum HPP</p>
        <p class="mt-1">Sistem belum menyimpan Harga Pokok Penjualan produk jadi. Karena itu, hasil di bawah belum memperhitungkan biaya bahan yang digunakan untuk produk terjual.</p>
    </div>

    <form method="GET" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 print:hidden">
        <x-ui.input label="Tanggal Mulai" name="start_date" type="date" :value="$startDate" required />
        <x-ui.input label="Tanggal Akhir" name="end_date" type="date" :value="$endDate" required />
        <div class="flex items-end">
            <x-ui.button type="submit" variant="secondary" class="w-full">Tampilkan</x-ui.button>
        </div>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Total pendapatan" :value="'Rp '.number_format((float) $totalIncome, 0, ',', '.')" tone="green" />
        <x-ui.stat-card label="Beban operasional" :value="'Rp '.number_format((float) $operatingExpenses, 0, ',', '.')" tone="red" />
        <x-ui.stat-card :label="$profitBeforeHpp >= 0 ? 'Laba sebelum HPP' : 'Rugi sebelum HPP'" :value="'Rp '.number_format(abs((float) $profitBeforeHpp), 0, ',', '.')" :tone="$profitBeforeHpp >= 0 ? 'green' : 'red'" />
    </section>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Rincian Laba Rugi</h2>
            <p class="mt-1 text-sm text-slate-500">Periode {{ date('d/m/Y', strtotime($startDate)) }}–{{ date('d/m/Y', strtotime($endDate)) }}</p>
        </div>

        <div class="divide-y divide-slate-100 text-sm">
            <div class="bg-slate-50 px-5 py-3 font-semibold uppercase tracking-wide text-slate-600">Pendapatan</div>
            <div class="flex items-center justify-between gap-4 px-5 py-3">
                <span class="text-slate-700">Penjualan terposting</span>
                <span class="font-medium text-slate-900">Rp {{ number_format((float) $salesRevenue, 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 px-5 py-3">
                <span class="text-slate-700">Pemasukan lain-lain</span>
                <span class="font-medium text-slate-900">Rp {{ number_format((float) $otherIncome, 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 bg-emerald-50 px-5 py-3 font-semibold text-emerald-900">
                <span>Total Pendapatan</span>
                <span>Rp {{ number_format((float) $totalIncome, 0, ',', '.') }}</span>
            </div>

            <div class="bg-slate-50 px-5 py-3 font-semibold uppercase tracking-wide text-slate-600">Beban Operasional</div>
            @forelse ($expenseRows as $expense)
                <div class="flex items-center justify-between gap-4 px-5 py-3">
                    <span class="text-slate-700">{{ $expense->label }}</span>
                    <span class="font-medium text-slate-900">Rp {{ number_format((float) $expense->total, 0, ',', '.') }}</span>
                </div>
            @empty
                <div class="px-5 py-4 text-slate-500">Belum ada pengeluaran operasional pada periode ini.</div>
            @endforelse
            <div class="flex items-center justify-between gap-4 bg-red-50 px-5 py-3 font-semibold text-red-900">
                <span>Total Beban Operasional</span>
                <span>Rp {{ number_format((float) $operatingExpenses, 0, ',', '.') }}</span>
            </div>

            <div class="flex items-center justify-between gap-4 px-5 py-5 text-base font-bold {{ $profitBeforeHpp >= 0 ? 'bg-emerald-100 text-emerald-950' : 'bg-red-100 text-red-950' }}">
                <span>{{ $profitBeforeHpp >= 0 ? 'Laba Sebelum HPP' : 'Rugi Sebelum HPP' }}</span>
                <span>Rp {{ number_format(abs((float) $profitBeforeHpp), 0, ',', '.') }}</span>
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-900">Informasi Pembelian Bahan</p>
        <div class="mt-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <p class="text-sm text-slate-500">Total penerimaan pembelian pada periode ini. Nilai ini belum dikurangkan sebagai HPP karena sebagian bahan dapat masih tersimpan sebagai stok.</p>
            <p class="shrink-0 text-lg font-semibold text-slate-900">Rp {{ number_format((float) $purchaseReceived, 0, ',', '.') }}</p>
        </div>
    </section>

    <p class="mt-4 text-xs text-slate-500">Pembayaran piutang pelanggan dan pembayaran hutang supplier tidak dihitung ulang karena nilai transaksinya sudah tercatat pada penjualan atau pembelian.</p>
</x-app-layout>
