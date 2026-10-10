<x-app-layout title="Produk" module="inventory" :module-data="config('modules.inventory')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Produk & Bahan</h1>
            <p class="mt-2 text-sm text-slate-500">Bahan baku, bahan kemasan, barang setengah jadi, dan produk jadi.</p>
        </div>
        @if (auth()->user()->hasPermission('inventory.manage'))
            <a href="{{ route('products.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm">+ Tambah Produk</a>
        @endif
    </div>

    <form method="GET" class="mt-7 flex gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="search" value="{{ $search }}" placeholder="Cari SKU atau nama produk..." class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">SKU</th>
                        <th class="px-5 py-3">Produk</th>
                        <th class="px-5 py-3">Jenis</th>
                        <th class="px-5 py-3">Minimum</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-5 py-4 font-medium">{{ $product->sku }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                <p class="text-xs text-slate-500">{{ $product->unit->name }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $product->type->label() }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ number_format((float) $product->minimum_stock, 0, ',', '.') }} {{ $product->unit->code }}</td>
                            <td class="px-5 py-4"><x-ui.badge tone="green">Aktif</x-ui.badge></td>
                            <td class="px-5 py-4 text-right">
                                @if (auth()->user()->hasPermission('inventory.manage'))
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('products.edit', $product) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini dari master? Histori transaksi tetap disimpan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6"><x-ui.empty-state title="Belum ada produk" description="Tambahkan bahan atau produk pertama untuk memulai pengelolaan stok." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 p-3 md:hidden">
            @forelse ($products as $product)
                <article class="rounded-lg border border-slate-200 p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="text-xs text-slate-500">{{ $product->sku }}</p>
                            <h2 class="mt-1 font-semibold">{{ $product->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $product->type->label() }} · {{ $product->unit->code }}</p>
                        </div>
                        <x-ui.badge tone="green">Aktif</x-ui.badge>
                    </div>
                    @if (auth()->user()->hasPermission('inventory.manage'))
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a href="{{ route('products.edit', $product) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-100 px-3 text-sm font-semibold">Edit produk</a>
                            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini dari master? Histori transaksi tetap disimpan.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600">Hapus</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <x-ui.empty-state title="Belum ada produk" description="Tambahkan bahan atau produk pertama." />
            @endforelse
        </div>
    </div>

    <div class="mt-5">{{ $products->links() }}</div>
</x-app-layout>
