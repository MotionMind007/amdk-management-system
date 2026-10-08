<x-app-layout title="Penyesuaian Stok" module="inventory" :module-data="config('modules.inventory')">
    <div class="max-w-xl">
        <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-brand-700">&larr; Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Penyesuaian Stok</h1>
        <p class="mt-2 text-sm text-slate-500">Koreksi stok selalu disimpan pada riwayat dan audit log.</p>
        <form method="POST" action="{{ route('inventory.adjustment.store') }}" class="mt-6 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <div class="grid gap-1.5">
                <label for="product_id" class="text-sm font-medium">Produk / Bahan</label>
                <select id="product_id" name="product_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->sku }} — {{ $product->name }} ({{ $product->unit->code }})</option>
                    @endforeach
                </select>
                @error('product_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-1.5">
                <label for="warehouse_id" class="text-sm font-medium">Gudang</label>
                <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-1.5">
                <label for="direction" class="text-sm font-medium">Arah Koreksi</label>
                <select id="direction" name="direction" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="in" @selected(old('direction') === 'in')>Tambah stok</option>
                    <option value="out" @selected(old('direction') === 'out')>Kurangi stok</option>
                </select>
            </div>
            <x-ui.input label="Jumlah" name="quantity" type="number" min="0.001" step="0.001" :value="old('quantity')" required />
            <x-ui.input label="Alasan Penyesuaian" name="notes" :value="old('notes')" placeholder="Contoh: Selisih hasil stock opname" required />
            <div class="flex justify-end"><x-ui.button type="submit">Simpan Penyesuaian</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
