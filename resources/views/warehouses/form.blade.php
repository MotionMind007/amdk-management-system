<x-app-layout :title="$warehouse->exists ? 'Edit Gudang' : 'Tambah Gudang'" module="inventory" :module-data="config('modules.inventory')">
    <div class="max-w-3xl">
        <a href="{{ route('warehouses.index') }}" class="text-sm font-semibold text-brand-700">&larr; Kembali ke daftar</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">{{ $warehouse->exists ? 'Edit Gudang' : 'Tambah Gudang' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Setiap gudang memiliki saldo stok dan riwayat pergerakan yang terpisah.</p>

        <form method="POST" action="{{ $warehouse->exists ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" class="mt-7 grid gap-6">
            @csrf
            @if ($warehouse->exists)
                @method('PUT')
            @endif

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Informasi Gudang</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Kode Gudang" name="code" :value="$warehouse->code" required placeholder="Contoh: GDG-TIMUR" />
                    <x-ui.input label="Nama Gudang" name="name" :value="$warehouse->name" required placeholder="Contoh: Gudang Timur" />
                    <div class="grid gap-1.5 sm:col-span-2">
                        <label for="address" class="text-sm font-medium text-slate-700">Alamat <span class="font-normal text-slate-400">(opsional)</span></label>
                        <textarea id="address" name="address" rows="4" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Alamat lokasi gudang">{{ old('address', $warehouse->address) }}</textarea>
                        @error('address')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <label for="is_active" class="text-sm font-medium text-slate-700">Status</label>
                        <select id="is_active" name="is_active" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="1" @selected((string) old('is_active', $warehouse->is_active ?? true) === '1')>Aktif</option>
                            <option value="0" @selected((string) old('is_active', $warehouse->is_active ?? true) === '0')>Tidak aktif</option>
                        </select>
                        @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p class="text-xs leading-5 text-slate-500">Gudang tidak aktif tidak dapat dipilih untuk transaksi baru, tetapi histori dan saldo lamanya tetap tersimpan.</p>
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('warehouses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Batal</a>
                <x-ui.button type="submit">{{ $warehouse->exists ? 'Simpan Perubahan' : 'Simpan Gudang' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
