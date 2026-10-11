<x-app-layout title="Buat Surat Jalan" module="inventory" :module-data="config('modules.inventory')">
    <div class="max-w-5xl">
        <a href="{{ route('sales.deliveries.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <div class="mt-4"><p class="text-sm font-semibold text-brand-700">Surat Jalan</p><h1 class="mt-1 text-2xl font-semibold">{{ $sale->number }}</h1><p class="mt-2 text-sm text-slate-500">{{ $sale->customer->name }} · Gudang {{ $sale->warehouse->name }}</p></div>

        <form method="POST" action="{{ route('sales.deliveries.store', $sale) }}" class="mt-7 grid gap-6">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Str::uuid()) }}">
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <x-ui.input label="Tanggal Pengiriman" name="delivery_date" type="date" :value="old('delivery_date', today()->format('Y-m-d'))" required />
                <div class="grid gap-1.5"><label for="notes" class="text-sm font-medium text-slate-700">Catatan Pengiriman</label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Barang yang Dikirim</h2><p class="mt-1 text-sm text-slate-500">Boleh dikirim sebagian. Sisa barang tetap muncul pada daftar pengiriman.</p></div>
                @error('quantities')<p class="mx-5 mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Dipesan</th><th class="px-5 py-3">Sudah Dikirim</th><th class="px-5 py-3">Kirim Sekarang</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sale->items as $item)
                                @php($delivered = (float) ($item->delivery_note_items_sum_quantity ?? 0))
                                @php($remaining = max(0, (float) $item->quantity - $delivered))
                                <tr>
                                    <td class="px-5 py-4 font-medium">{{ $item->product->name }} <span class="text-xs text-slate-500">({{ $item->product->unit->code }})</span></td>
                                    <td class="px-5 py-4">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                    <td class="px-5 py-4">{{ number_format($delivered, 3, ',', '.') }}</td>
                                    <td class="px-5 py-4"><input name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id) }}" type="number" min="0" max="{{ $remaining }}" step="0.001" class="min-h-11 w-40 rounded-lg border border-slate-300 px-3 text-sm" @disabled($remaining <= 0)></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="flex justify-end"><x-ui.button type="submit">Posting Surat Jalan</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
