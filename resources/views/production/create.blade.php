@php
    $selectedProductionType = old('production_type', $production->production_type?->value ?? \App\ProductionType::FinishedGood->value);
@endphp

<x-app-layout :title="$production->exists ? 'Edit Rekap Produksi' : 'Buat Rekap Produksi'" module="production" :module-data="config('modules.production')">
    <div class="max-w-4xl">
        <a href="{{ route('production.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">{{ $production->exists ? 'Edit Rekap Produksi' : 'Rekap Produksi Harian' }}</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ $production->exists ? "Perbarui data {$production->number} sebelum diposting ke stok." : 'Pilih produksi produk jadi otomatis atau produksi kemasan dengan pemakaian bahan aktual.' }}
        </p>

        <form method="POST" action="{{ $production->exists ? route('production.update', $production) : route('production.store') }}" class="mt-7 grid gap-6" data-production-form>
            @csrf
            @if ($production->exists)
                @method('PUT')
            @endif

            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="production_type" class="text-sm font-medium">Jenis Produksi</label>
                    <select id="production_type" name="production_type" required data-production-type class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        @foreach (\App\ProductionType::cases() as $productionType)
                            <option value="{{ $productionType->value }}" @selected($selectedProductionType === $productionType->value)>{{ $productionType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500" data-production-description></p>
                </div>
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

            <section data-production-section="finished_good" @class(['rounded-xl border border-slate-200 bg-white p-5 shadow-sm', 'hidden' => $selectedProductionType !== \App\ProductionType::FinishedGood->value])>
                <h2 class="font-semibold">Hasil Produk Jadi</h2>
                <p class="mt-1 text-sm text-slate-500">Masukkan hasil dalam karton. Bahan akan dihitung otomatis dari komposisi produk.</p>
                <div class="mt-5">
                    <x-ui.item-rows :products="$finishedProducts" mode="production" :items="$production->production_type === \App\ProductionType::FinishedGood ? $production->items : []" id-prefix="finished-item" />
                </div>
            </section>

            <section data-production-section="packaging" @class(['grid gap-6', 'hidden' => $selectedProductionType !== \App\ProductionType::Packaging->value])>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold">Hasil Produksi Kemasan</h2>
                    <p class="mt-1 text-sm text-slate-500">Masukkan botol atau cup yang berhasil diproduksi. Reject dicatat tetapi tidak menambah stok.</p>
                    <div class="mt-5">
                        <x-ui.item-rows :products="$packagingProducts" mode="production" :items="$production->production_type === \App\ProductionType::Packaging ? $production->items : []" id-prefix="packaging-item" />
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold">Bahan Aktual Terpakai</h2>
                    <p class="mt-1 text-sm text-slate-500">Masukkan jumlah preform atau bahan lain yang benar-benar dipakai selama produksi.</p>
                    <div class="mt-5">
                        <x-ui.item-rows :products="$materialProducts" mode="material" field="materials" :items="$production->materials" id-prefix="material-item" />
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('production.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold">Batal</a>
                <x-ui.button type="submit">{{ $production->exists ? 'Simpan Perubahan' : 'Simpan Draft' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
