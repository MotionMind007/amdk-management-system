<x-app-layout :title="$openingStock->exists ? 'Edit Stok Awal' : 'Input Stok Awal'" module="system" :module-data="config('modules.system')">
    <div class="max-w-5xl">
        <a href="{{ route('admin.opening-stocks.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">{{ $openingStock->exists ? 'Edit Stok Awal' : 'Input Stok Awal' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Masukkan saldo fisik saat perpindahan dari pencatatan manual. Data belum memengaruhi stok sebelum diposting.</p>

        <form method="POST" action="{{ $openingStock->exists ? route('admin.opening-stocks.update', $openingStock) : route('admin.opening-stocks.store') }}" class="mt-7 grid gap-6">
            @csrf
            @if ($openingStock->exists)
                @method('PUT')
            @endif

            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <x-ui.input label="Tanggal Stok Awal" name="stock_date" type="date" :value="$openingStock->stock_date?->format('Y-m-d') ?? today()->format('Y-m-d')" :max="today()->format('Y-m-d')" required />
                <div class="grid gap-1.5">
                    <label for="warehouse_id" class="text-sm font-medium text-slate-700">Gudang <span class="text-red-600">*</span></label>
                    <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $openingStock->warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="notes" class="text-sm font-medium text-slate-700">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea id="notes" name="notes" rows="3" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: Hasil stock opname sebelum mulai menggunakan sistem">{{ old('notes', $openingStock->notes) }}</textarea>
                    @error('notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Daftar Produk dan Bahan</h2>
                <p class="mt-1 text-sm text-slate-500">Gunakan satuan utama masing-masing barang. Produk yang sama tidak boleh dimasukkan dua kali.</p>
                <div class="mt-5">
                    <x-ui.item-rows :products="$products" mode="opening" :items="$openingStock->exists ? $openingStock->items : []" />
                </div>
                @error('items')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('items.*.product_id')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('items.*.quantity')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.opening-stocks.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Batal</a>
                <x-ui.button type="submit">{{ $openingStock->exists ? 'Simpan Perubahan' : 'Simpan Draft' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
