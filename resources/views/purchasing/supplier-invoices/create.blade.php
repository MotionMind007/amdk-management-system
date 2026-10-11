<x-app-layout title="Tagihan Supplier" module="purchasing" :module-data="config('modules.purchasing')">
    <div class="max-w-4xl">
        <a href="{{ route('purchasing.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <div class="mt-4">
            <p class="text-sm font-semibold text-brand-700">Tagihan Supplier</p>
            <h1 class="mt-1 text-2xl font-semibold">Catat Faktur {{ $goodsReceipt->number }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $goodsReceipt->purchaseOrder->supplier->name }} · PO {{ $goodsReceipt->purchaseOrder->number }}</p>
        </div>

        <form method="POST" action="{{ route('purchasing.supplier-invoices.store', $goodsReceipt) }}" enctype="multipart/form-data" class="mt-7 grid gap-6">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Str::uuid()) }}">
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <x-ui.input label="Nomor Faktur Supplier" name="supplier_invoice_number" :value="old('supplier_invoice_number')" required />
                <x-ui.input label="Tanggal Faktur" name="invoice_date" type="date" :value="old('invoice_date', today()->format('Y-m-d'))" required />
                <x-ui.input label="Jatuh Tempo" name="due_date" type="date" :value="old('due_date', $goodsReceipt->purchaseOrder->due_date?->format('Y-m-d'))" />
                <x-ui.input label="Nilai Tagihan" name="total" type="number" min="1" step="1" :value="old('total', $goodsReceipt->total)" required />
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="proof" class="text-sm font-medium text-slate-700">Upload Faktur Supplier <span class="text-slate-400">(opsional)</span></label>
                    <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <p class="text-xs text-slate-500">JPG, PNG, WebP, atau PDF maksimal 5 MB.</p>
                    @error('proof')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="notes" class="text-sm font-medium text-slate-700">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Referensi Barang Diterima</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Nilai</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($goodsReceipt->items as $item)
                                <tr><td class="px-5 py-4">{{ $item->product->name }}</td><td class="px-5 py-4 text-right">{{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->product->unit->code }}</td><td class="px-5 py-4 text-right">Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end"><x-ui.button type="submit">Simpan Tagihan Supplier</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
