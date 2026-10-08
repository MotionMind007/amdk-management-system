<x-app-layout :title="$production->exists ? 'Edit Rekap Produksi' : 'Buat Rekap Produksi'" module="production" :module-data="config('modules.production')">
    <div class="max-w-4xl">
        <a href="{{ route('production.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">{{ $production->exists ? 'Edit Rekap Produksi' : 'Rekap Produksi Harian' }}</h1>
        @if ($production->exists)
            <p class="mt-2 text-sm text-slate-500">Perbarui data {{ $production->number }} sebelum diposting ke stok.</p>
        @endif

        <form method="POST" action="{{ $production->exists ? route('production.update', $production) : route('production.store') }}" class="mt-7 grid gap-6">
            @csrf
            @if ($production->exists)
                @method('PUT')
            @endif

            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <x-ui.input label="Tanggal Produksi" name="production_date" type="date" :value="$production->production_date?->format('Y-m-d') ?? today()->format('Y-m-d')" required />
                <div class="grid gap-1.5">
                    <label for="warehouse_id" class="text-sm font-medium">Gudang</label>
                    <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $production->warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="notes" class="text-sm font-medium">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $production->notes) }}</textarea>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Hasil Produksi</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah menggunakan satuan utama produk, yaitu karton untuk produk jadi.</p>
                <div class="mt-5">
                    <x-ui.item-rows :products="$products" mode="production" :items="$production->exists ? $production->items : []" />
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('production.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold">Batal</a>
                <x-ui.button type="submit">{{ $production->exists ? 'Simpan Perubahan' : 'Simpan Draft' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
