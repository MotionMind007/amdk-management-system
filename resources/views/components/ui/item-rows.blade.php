@props(['products', 'mode' => 'transaction', 'items' => []])

@php
    $options = $products->map(fn ($product) => '<option value="'.$product->id.'">'.e($product->name).' ('.e($product->unit->code).')</option>')->implode('');
    $rows = old('items');

    if ($rows === null) {
        $rows = collect($items)
            ->map(fn ($item) => [
                'product_id' => data_get($item, 'product_id'),
                'quantity' => data_get($item, 'quantity'),
                'rejected_quantity' => data_get($item, 'rejected_quantity'),
                'unit_price' => data_get($item, 'unit_price'),
            ])
            ->all();
    }

    if ($rows === []) {
        $rows[] = [
            'product_id' => '',
            'quantity' => '',
            'rejected_quantity' => 0,
            'unit_price' => '',
        ];
    }
@endphp

<div data-repeater class="grid gap-3">
    <div data-repeater-items class="grid gap-3">
        @foreach ($rows as $index => $row)
            <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-12">
                <div @class(['min-w-0 sm:col-span-2', 'xl:col-span-5' => $mode === 'production', 'xl:col-span-7' => $mode === 'opening', 'xl:col-span-4' => ! in_array($mode, ['production', 'opening'], true)])>
                    <label for="item-product-{{ $index }}" class="text-xs font-medium text-slate-600">Produk</label>
                    <select id="item-product-{{ $index }}" name="items[{{ $index }}][product_id]" required class="mt-1 min-h-11 w-full min-w-0 max-w-full rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Pilih produk</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((int) data_get($row, 'product_id') === $product->id)>{{ $product->name }} ({{ $product->unit->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0 xl:col-span-3">
                    <label for="item-quantity-{{ $index }}" class="text-xs font-medium text-slate-600">Jumlah</label>
                    <input id="item-quantity-{{ $index }}" name="items[{{ $index }}][quantity]" value="{{ data_get($row, 'quantity') }}" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                @if ($mode === 'production')
                    <div class="min-w-0 xl:col-span-2">
                        <label for="item-rejected-{{ $index }}" class="text-xs font-medium text-slate-600">Reject</label>
                        <input id="item-rejected-{{ $index }}" name="items[{{ $index }}][rejected_quantity]" value="{{ data_get($row, 'rejected_quantity', 0) }}" type="number" min="0" step="0.001" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                @elseif ($mode !== 'opening')
                    <div class="min-w-0 xl:col-span-3">
                        <label for="item-price-{{ $index }}" class="text-xs font-medium text-slate-600">Harga/Satuan</label>
                        <input id="item-price-{{ $index }}" name="items[{{ $index }}][unit_price]" value="{{ data_get($row, 'unit_price') }}" type="number" min="0" step="1" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                @endif
                <div class="flex items-end sm:col-span-2 xl:col-span-2">
                    <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                </div>
            </div>
        @endforeach
    </div>

    <template>
        <div data-repeater-row class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-12">
            <div @class(['min-w-0 sm:col-span-2', 'xl:col-span-5' => $mode === 'production', 'xl:col-span-7' => $mode === 'opening', 'xl:col-span-4' => ! in_array($mode, ['production', 'opening'], true)])>
                <label for="item-product-__INDEX__" class="text-xs font-medium text-slate-600">Produk</label>
                <select id="item-product-__INDEX__" name="items[__INDEX__][product_id]" required class="mt-1 min-h-11 w-full min-w-0 max-w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="">Pilih produk</option>
                    {!! $options !!}
                </select>
            </div>
            <div class="min-w-0 xl:col-span-3">
                <label for="item-quantity-__INDEX__" class="text-xs font-medium text-slate-600">Jumlah</label>
                <input id="item-quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            @if ($mode === 'production')
                <div class="min-w-0 xl:col-span-2">
                    <label for="item-rejected-__INDEX__" class="text-xs font-medium text-slate-600">Reject</label>
                    <input id="item-rejected-__INDEX__" name="items[__INDEX__][rejected_quantity]" type="number" min="0" step="0.001" value="0" class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @elseif ($mode !== 'opening')
                <div class="min-w-0 xl:col-span-3">
                    <label for="item-price-__INDEX__" class="text-xs font-medium text-slate-600">Harga/Satuan</label>
                    <input id="item-price-__INDEX__" name="items[__INDEX__][unit_price]" type="number" min="0" step="1" required class="mt-1 min-h-11 w-full min-w-0 rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            @endif
            <div class="flex items-end sm:col-span-2 xl:col-span-2">
                <button type="button" data-repeater-remove class="min-h-11 w-full rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
            </div>
        </div>
    </template>

    <button type="button" data-repeater-add class="justify-self-start rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">+ Tambah Baris</button>
</div>
