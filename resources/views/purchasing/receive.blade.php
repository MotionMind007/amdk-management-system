<x-app-layout title="Penerimaan Barang" module="purchasing" :module-data="config('modules.purchasing')">
    <div class="max-w-4xl">
        <a href="{{ route('purchasing.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Approve Penerimaan {{ $purchaseOrder->number }}</h1>
        <p class="mt-2 text-sm text-slate-500">Supplier: {{ $purchaseOrder->supplier->name }}. Periksa jumlah yang tiba dan unggah bukti sebelum posting.</p>

        <form method="POST" action="{{ route('purchasing.receive.store', $purchaseOrder) }}" enctype="multipart/form-data" class="mt-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            <div class="grid gap-4">
                @foreach ($purchaseOrder->items as $item)
                    @php($remaining = (float) $item->ordered_quantity - (float) $item->received_quantity)
                    <div class="grid items-end gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-3">
                        <div>
                            <p class="font-medium">{{ $item->product->name }}</p>
                            <p class="text-sm text-slate-500">Sisa {{ number_format($remaining, 0, ',', '.') }} {{ $item->product->unit->code }}</p>
                        </div>
                        <x-ui.input label="Jumlah Diterima" :name="'quantities['.$item->id.']'" type="number" min="0" :max="$remaining" step="0.001" :value="old('quantities.'.$item->id, $remaining)" />
                        <p class="pb-3 text-sm text-slate-500">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}/{{ $item->product->unit->code }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5">
                <label for="proof" class="block text-sm font-medium text-slate-700">Bukti Penerimaan Barang <span class="text-red-600">*</span></label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs text-slate-500">Format JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                @error('proof')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Posting Penerimaan</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
