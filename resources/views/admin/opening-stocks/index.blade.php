<x-app-layout title="Stok Awal" module="system" :module-data="config('modules.system')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Migrasi Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Stok Awal</h1>
            <p class="mt-2 text-sm text-slate-500">Masukkan saldo stok saat perusahaan mulai beralih dari pencatatan manual.</p>
        </div>
        <a href="{{ route('admin.opening-stocks.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">+ Input Stok Awal</a>
    </div>

    <div class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Setelah diposting, stok awal tidak dapat diedit. Koreksi berikutnya dilakukan melalui menu Penyesuaian Stok.
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Nomor / Tanggal</th><th class="px-5 py-3">Gudang</th><th class="px-5 py-3">Isi</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($openingStocks as $openingStock)
                        <tr>
                            <td class="px-5 py-4"><p class="font-semibold text-slate-800">{{ $openingStock->number }}</p><p class="text-xs text-slate-500">{{ $openingStock->stock_date->format('d/m/Y') }}</p></td>
                            <td class="px-5 py-4 text-slate-700">{{ $openingStock->warehouse->name }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $openingStock->items->count() }} barang</td>
                            <td class="px-5 py-4"><x-ui.badge :tone="$openingStock->status === 'posted' ? 'green' : 'gray'">{{ $openingStock->status === 'posted' ? 'Terposting' : 'Draft' }}</x-ui.badge></td>
                            <td class="px-5 py-4">
                                @if ($openingStock->status === 'draft')
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.opening-stocks.edit', $openingStock) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700">Edit</a>
                                        <form method="POST" action="{{ route('admin.opening-stocks.post', $openingStock) }}" onsubmit="return confirm('Posting stok awal? Data tidak dapat diedit setelah diposting.')">
                                            @csrf
                                            <x-ui.button type="submit">Posting</x-ui.button>
                                        </form>
                                    </div>
                                @else
                                    <p class="text-right text-xs text-slate-500">Terkunci</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada stok awal" description="Buat draft stok awal untuk memulai migrasi data." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $openingStocks->links() }}</div>
</x-app-layout>
