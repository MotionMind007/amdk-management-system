@props(['products', 'mode' => 'transaction'])

@php
    $options = $products->map(fn ($product) => '<option value="'.$product->id.'">'.e($product->name).' ('.e($product->unit->code).')</option>')->implode('');
@endphp

<div data-repeater class="grid gap-3">
    <div data-repeater-items class="grid gap-3">
        <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-12">
            <div @class(['min-w-0 sm:col-span-2', 'xl:col-span-5' => $mode === 'production', 'xl:col-span-4' => $mode !== 'production'])>
                <label class="text-xs font-medium text-slate-600">Produk</label>
                <select name="items[0][product_id]" required class="mt-1 min-h-11 min-w-0 w-full max-w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Pilih produk</option>
                    {!! $options !!}
                </select>
            </div>
            <div class="min-w-0 xl:col-span-3">
                <label class="text-xs font-medium text-slate-600">Jumlah</label>
                <input name="items[0][quantity]" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            @if ($mode === 'production')
                <div class="min-w-0 xl:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Reject</label>
                    <input name="items[0][rejected_quantity]" type="number" min="0" step="0.001" value="0" class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @else
                <div class="min-w-0 xl:col-span-3">
                    <label class="text-xs font-medium text-slate-600">Harga/Satuan</label>
                    <input name="items[0][unit_price]" type="number" min="0" step="1" required class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @endif
            <div class="flex items-end sm:col-span-2 xl:col-span-2">
                <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
            </div>
        </div>
    </div>

    <template>
        <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-12">
            <div @class(['min-w-0 sm:col-span-2', 'xl:col-span-5' => $mode === 'production', 'xl:col-span-4' => $mode !== 'production'])>
                <label class="text-xs font-medium text-slate-600">Produk</label>
                <select name="items[__INDEX__][product_id]" required class="mt-1 min-h-11 min-w-0 w-full max-w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Pilih produk</option>
                    {!! $options !!}
                </select>
            </div>
            <div class="min-w-0 xl:col-span-3">
                <label class="text-xs font-medium text-slate-600">Jumlah</label>
                <input name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            @if ($mode === 'production')
                <div class="min-w-0 xl:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Reject</label>
                    <input name="items[__INDEX__][rejected_quantity]" type="number" min="0" step="0.001" value="0" class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @else
                <div class="min-w-0 xl:col-span-3">
                    <label class="text-xs font-medium text-slate-600">Harga/Satuan</label>
                    <input name="items[__INDEX__][unit_price]" type="number" min="0" step="1" required class="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @endif
            <div class="flex items-end sm:col-span-2 xl:col-span-2">
                <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
            </div>
        </div>
    </template>

    <button type="button" data-repeater-add class="justify-self-start rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">+ Tambah Baris</button>
</div>
