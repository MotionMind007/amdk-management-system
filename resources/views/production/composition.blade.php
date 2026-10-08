<x-app-layout title="Komposisi Produk" module="production" :module-data="config('modules.production')">
    @php
        $compositionRows = old('components');

        if ($compositionRows === null) {
            $compositionRows = $product->compositions
                ->map(fn ($composition) => [
                    'product_id' => $composition->component_product_id,
                    'quantity' => $composition->quantity,
                ])
                ->all();
        }

        if ($compositionRows === []) {
            $compositionRows[] = ['product_id' => '', 'quantity' => ''];
        }
    @endphp

    <div class="max-w-3xl">
        <a href="{{ route('production.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Komposisi {{ $product->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Masukkan kebutuhan bahan untuk menghasilkan 1 {{ $product->unit->code }} produk jadi.</p>

        <form method="POST" action="{{ route('production.compositions.update', $product) }}" class="mt-7 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            @method('PUT')

            <div data-repeater class="grid gap-4">
                <div data-repeater-items class="grid gap-3">
                    @foreach ($compositionRows as $index => $composition)
                        <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
                            <div class="min-w-0 sm:col-span-6">
                                <label for="component-{{ $index }}" class="text-xs font-medium text-slate-600">Bahan</label>
                                <select id="component-{{ $index }}" name="components[{{ $index }}][product_id]" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                                    <option value="">Pilih bahan</option>
                                    @foreach ($components as $component)
                                        <option value="{{ $component->id }}" @selected((int) data_get($composition, 'product_id') === $component->id)>{{ $component->name }} ({{ $component->unit->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0 sm:col-span-4">
                                <label for="component-quantity-{{ $index }}" class="text-xs font-medium text-slate-600">Jumlah</label>
                                <input id="component-quantity-{{ $index }}" name="components[{{ $index }}][quantity]" value="{{ data_get($composition, 'quantity') }}" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                            </div>
                            <div class="flex items-end sm:col-span-2">
                                <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template>
                    <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
                        <div class="min-w-0 sm:col-span-6">
                            <label for="component-__INDEX__" class="text-xs font-medium text-slate-600">Bahan</label>
                            <select id="component-__INDEX__" name="components[__INDEX__][product_id]" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                                <option value="">Pilih bahan</option>
                                @foreach ($components as $component)
                                    <option value="{{ $component->id }}">{{ $component->name }} ({{ $component->unit->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0 sm:col-span-4">
                            <label for="component-quantity-__INDEX__" class="text-xs font-medium text-slate-600">Jumlah</label>
                            <input id="component-quantity-__INDEX__" name="components[__INDEX__][quantity]" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                        </div>
                        <div class="flex items-end sm:col-span-2">
                            <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                        </div>
                    </div>
                </template>

                <button type="button" data-repeater-add class="justify-self-start rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100">+ Tambah Bahan</button>
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit">Simpan Komposisi</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
