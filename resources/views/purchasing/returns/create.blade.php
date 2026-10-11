<x-app-layout title="Retur Pembelian" module="purchasing" :module-data="config('modules.purchasing')">
    <div class="max-w-5xl">
        <a href="{{ route('purchasing.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <div class="mt-4">
            <p class="text-sm font-semibold text-brand-700">Retur Pembelian</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ $goodsReceipt->number }}</h1>
            <p class="mt-2 text-sm text-slate-500">PO {{ $goodsReceipt->purchaseOrder->number }} · {{ $goodsReceipt->purchaseOrder->supplier->name }} · Gudang {{ $goodsReceipt->purchaseOrder->warehouse->name }}</p>
        </div>

        <form method="POST" action="{{ route('purchasing.returns.store', $goodsReceipt) }}" class="mt-7 grid gap-6">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Str::uuid()) }}">
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <x-ui.input label="Tanggal Retur" name="return_date" type="date" :value="today()->format('Y-m-d')" required />
                <div class="grid gap-1.5">
                    <label for="refund_account_id" class="text-sm font-medium text-slate-700">Akun Penerima Refund</label>
                    <select id="refund_account_id" name="refund_account_id" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Tidak ada refund tunai</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('refund_account_id') === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">Wajib dipilih bila barang yang diretur sebelumnya sudah dibayar.</p>
                    @error('refund_account_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="reason" class="text-sm font-medium text-slate-700">Alasan Retur <span class="text-red-600">*</span></label>
                    <textarea id="reason" name="reason" rows="3" required maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                    @error('reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold">Barang yang Diretur</h2>
                    <p class="mt-1 text-sm text-slate-500">Isi jumlah fisik yang dikembalikan ke supplier.</p>
                </div>
                @error('quantities')<p class="mx-5 mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr><th class="px-5 py-3">Barang</th><th class="px-5 py-3">Diterima</th><th class="px-5 py-3">Sudah Diretur</th><th class="px-5 py-3">Retur Sekarang</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($goodsReceipt->items as $item)
                                @php($returned = (float) ($item->return_items_sum_quantity ?? 0))
                                @php($remaining = max(0, (float) $item->quantity - $returned))
                                <tr>
                                    <td class="px-5 py-4 font-medium">{{ $item->product->name }} <span class="text-xs text-slate-500">({{ $item->product->unit->code }})</span></td>
                                    <td class="px-5 py-4">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                    <td class="px-5 py-4">{{ number_format($returned, 3, ',', '.') }}</td>
                                    <td class="px-5 py-4"><input name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id) }}" type="number" min="0" max="{{ $remaining }}" step="0.001" class="min-h-11 w-36 rounded-lg border border-slate-300 px-3 text-sm" @disabled($remaining <= 0)></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end"><x-ui.button type="submit">Posting Retur Pembelian</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
