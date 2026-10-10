<x-app-layout :title="$stockOpname->exists ? 'Edit Stok Opname' : 'Buat Stok Opname'" module="inventory" :module-data="config('modules.inventory')">
    @php
        $selectedWarehouseId = (int) old('warehouse_id', $defaultWarehouseId);
    @endphp
    <div class="max-w-7xl">
        <a href="{{ route('inventory.opnames.index') }}" class="text-sm font-semibold text-brand-700">&larr; Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">{{ $stockOpname->exists ? 'Edit Stok Opname' : 'Buat Stok Opname Bulanan' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Isi jumlah fisik sesuai hasil penghitungan. Sistem akan menghitung selisih secara otomatis.</p>

        <form method="POST" action="{{ $stockOpname->exists ? route('inventory.opnames.update', $stockOpname) : route('inventory.opnames.store') }}" class="mt-7 grid gap-6" data-stock-opname-form data-snapshot-fixed="{{ $stockOpname->exists ? 'true' : 'false' }}">
            @csrf
            @if ($stockOpname->exists)
                @method('PUT')
            @endif

            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                @if ($stockOpname->exists)
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-slate-700">Periode</span>
                        <div class="flex min-h-11 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700">{{ $stockOpname->period }}</div>
                        <input type="hidden" name="period" value="{{ $stockOpname->period }}">
                    </div>
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-slate-700">Gudang</span>
                        <div class="flex min-h-11 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700">{{ $stockOpname->warehouse->name }}</div>
                        <input type="hidden" name="warehouse_id" value="{{ $stockOpname->warehouse_id }}" data-opname-warehouse>
                    </div>
                @else
                    <x-ui.input label="Periode" name="period" type="month" :value="old('period', today()->format('Y-m'))" :max="today()->format('Y-m')" required />
                    <div class="grid gap-1.5">
                        <label for="warehouse_id" class="text-sm font-medium text-slate-700">Gudang <span class="text-red-600">*</span></label>
                        <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm" data-opname-warehouse>
                            <option value="">Pilih gudang</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif

                <x-ui.input label="Tanggal Penghitungan" name="opname_date" type="date" :value="old('opname_date', $stockOpname->opname_date?->format('Y-m-d') ?? today()->format('Y-m-d'))" :max="today()->format('Y-m-d')" required />
                <div class="grid gap-1.5">
                    <label for="notes" class="text-sm font-medium text-slate-700">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea id="notes" name="notes" rows="2" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: Opname akhir bulan">{{ old('notes', $stockOpname->notes) }}</textarea>
                    @error('notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Data stok opname belum dapat disimpan.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="font-semibold">Hasil Penghitungan Fisik</h2>
                    <p class="mt-1 text-sm text-slate-500">Jumlah stok sistem merupakan snapshot saat draft pertama kali disimpan.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[960px] w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Produk / Bahan</th>
                                <th class="w-40 px-5 py-3">Stok Sistem</th>
                                <th class="w-48 px-5 py-3">Stok Fisik</th>
                                <th class="w-40 px-5 py-3">Selisih</th>
                                <th class="w-72 px-5 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($products as $index => $product)
                                @php
                                    $opnameItem = $stockOpname->exists ? $stockOpname->items->firstWhere('product_id', $product->id) : null;
                                    $systemQuantity = $opnameItem
                                        ? (float) $opnameItem->system_quantity
                                        : ($selectedWarehouseId ? (float) data_get($balanceMap, "{$product->id}.{$selectedWarehouseId}", 0) : null);
                                    $physicalQuantity = old("items.{$index}.physical_quantity", $opnameItem?->physical_quantity);
                                    $difference = $systemQuantity !== null && is_numeric($physicalQuantity) ? (float) $physicalQuantity - $systemQuantity : null;
                                @endphp
                                <tr class="align-top" data-opname-row data-product-id="{{ $product->id }}">
                                    <td class="px-5 py-4">
                                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}">
                                        <p class="font-medium text-slate-800">{{ $product->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $product->sku }} · {{ $product->unit->code }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-semibold text-slate-700" data-system-quantity>{{ $systemQuantity === null ? '—' : number_format($systemQuantity, 3, ',', '.') }}</span>
                                        <span class="text-xs text-slate-500">{{ $product->unit->code }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input name="items[{{ $index }}][physical_quantity]" type="number" min="0" step="0.001" value="{{ $physicalQuantity }}" required class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm" data-physical-quantity>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-semibold {{ $difference === null ? 'text-slate-400' : ($difference === 0.0 ? 'text-emerald-700' : 'text-amber-700') }}" data-difference>{{ $difference === null ? '—' : (($difference > 0 ? '+' : '').number_format($difference, 3, ',', '.')) }}</span>
                                        <span class="text-xs text-slate-500">{{ $product->unit->code }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input name="items[{{ $index }}][notes]" value="{{ old("items.{$index}.notes", $opnameItem?->notes) }}" maxlength="500" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="Penyebab selisih (opsional)">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($products->isEmpty())
                    <div class="p-6"><x-ui.empty-state title="Belum ada produk aktif" description="Tambahkan produk atau bahan terlebih dahulu sebelum membuat stok opname." /></div>
                @endif
            </section>

            <script type="application/json" data-opname-balance-map>@json($balanceMap)</script>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('inventory.opnames.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Batal</a>
                <x-ui.button type="submit" :disabled="$products->isEmpty()">{{ $stockOpname->exists ? 'Simpan Perubahan' : 'Simpan Draft' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
