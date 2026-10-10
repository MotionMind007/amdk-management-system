<x-app-layout title="Stok Opname" module="inventory" :module-data="config('modules.inventory')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Persediaan</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Stok Opname Bulanan</h1>
            <p class="mt-2 text-sm text-slate-500">Bandingkan stok sistem dengan hasil hitung fisik dan posting selisihnya.</p>
        </div>
        @if (auth()->user()->hasPermission('inventory.manage'))
            <a href="{{ route('inventory.opnames.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">+ Buat Stok Opname</a>
        @endif
    </div>

    <div class="mt-7 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
        Stok sistem disimpan sebagai snapshot saat draft dibuat. Setelah administrator mem-posting, selisih menjadi penyesuaian stok dan saldo akhir otomatis terbawa ke bulan berikutnya.
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Nomor / Periode</th>
                        <th class="px-5 py-3">Gudang</th>
                        <th class="px-5 py-3">Hasil</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($stockOpnames as $stockOpname)
                        @php($differenceCount = $stockOpname->items->filter(fn ($item) => $item->difference() !== 0.0)->count())
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-5 py-4">
                                <a href="{{ route('inventory.opnames.show', $stockOpname) }}" class="font-semibold text-brand-700 hover:underline">{{ $stockOpname->number }}</a>
                                <p class="mt-1 text-xs text-slate-500">Periode {{ $stockOpname->period }} · {{ $stockOpname->opname_date->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ $stockOpname->warehouse->name }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-700">{{ $stockOpname->items->count() }} barang dihitung</p>
                                <p class="mt-1 text-xs {{ $differenceCount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $differenceCount > 0 ? $differenceCount.' barang berselisih' : 'Tidak ada selisih' }}</p>
                            </td>
                            <td class="px-5 py-4"><x-ui.badge :tone="$stockOpname->status === 'posted' ? 'green' : 'gray'">{{ $stockOpname->status === 'posted' ? 'Terposting' : 'Draft' }}</x-ui.badge></td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('inventory.opnames.show', $stockOpname) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detail</a>
                                    @if ($stockOpname->status === 'draft' && auth()->user()->hasPermission('inventory.manage'))
                                        <a href="{{ route('inventory.opnames.edit', $stockOpname) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                    @endif
                                    @if ($stockOpname->status === 'draft' && auth()->user()->hasPermission('system.manage'))
                                        <form method="POST" action="{{ route('inventory.opnames.post', $stockOpname) }}" onsubmit="return confirm('Posting stok opname? Selisih akan langsung menyesuaikan saldo stok dan data tidak dapat diedit lagi.')">
                                            @csrf
                                            <x-ui.button type="submit">Posting</x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada stok opname" description="Buat stok opname bulanan untuk membandingkan saldo sistem dengan hasil hitung fisik." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $stockOpnames->links() }}</div>
</x-app-layout>
