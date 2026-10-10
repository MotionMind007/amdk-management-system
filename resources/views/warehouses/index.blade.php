<x-app-layout title="Master Gudang" module="inventory" :module-data="config('modules.inventory')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Gudang</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola lokasi penyimpanan agar saldo stok setiap gudang tercatat terpisah.</p>
        </div>
        @if (auth()->user()->hasPermission('system.manage'))
            <a href="{{ route('warehouses.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">+ Tambah Gudang</a>
        @endif
    </div>

    <form method="GET" class="mt-7 flex gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="search" value="{{ $search }}" placeholder="Cari kode, nama, atau alamat gudang..." class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Gudang</th><th class="px-5 py-3">Saldo Tercatat</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td class="px-5 py-4 font-medium text-slate-700">{{ $warehouse->code }}</td>
                            <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $warehouse->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $warehouse->address ?: 'Alamat belum diisi' }}</p></td>
                            <td class="px-5 py-4 text-slate-600">{{ $warehouse->stock_balances_count }} produk/bahan</td>
                            <td class="px-5 py-4"><x-ui.badge :tone="$warehouse->is_active ? 'green' : 'gray'">{{ $warehouse->is_active ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge></td>
                            <td class="px-5 py-4 text-right">
                                @if (auth()->user()->hasPermission('system.manage'))
                                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="font-semibold text-brand-700 hover:underline">Edit</a>
                                @else
                                    <span class="text-xs text-slate-400">Lihat saja</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada gudang" description="Administrator dapat menambahkan lokasi gudang pertama." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 p-3 md:hidden">
            @forelse ($warehouses as $warehouse)
                <article class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs text-slate-500">{{ $warehouse->code }}</p><h2 class="mt-1 font-semibold">{{ $warehouse->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $warehouse->address ?: 'Alamat belum diisi' }}</p></div>
                        <x-ui.badge :tone="$warehouse->is_active ? 'green' : 'gray'">{{ $warehouse->is_active ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge>
                    </div>
                    <p class="mt-4 text-sm text-slate-600">{{ $warehouse->stock_balances_count }} produk/bahan memiliki saldo di gudang ini.</p>
                    @if (auth()->user()->hasPermission('system.manage'))
                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="mt-4 block rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-semibold text-slate-700">Edit Gudang</a>
                    @endif
                </article>
            @empty
                <x-ui.empty-state title="Belum ada gudang" description="Administrator dapat menambahkan lokasi gudang pertama." />
            @endforelse
        </div>
    </div>

    <div class="mt-5">{{ $warehouses->links() }}</div>
</x-app-layout>
