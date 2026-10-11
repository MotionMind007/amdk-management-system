<x-app-layout title="Penjualan" module="sales" :module-data="config('modules.sales')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Penjualan</p>
            <h1 class="mt-1 text-2xl font-semibold">Transaksi Penjualan</h1>
            <p class="mt-2 text-sm text-slate-500">Posting membuat tagihan; stok baru berkurang saat gudang memposting surat jalan.</p>
        </div>
        <a href="{{ route('sales.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white">+ Buat Penjualan</a>
    </div>

    <div class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Invoice</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Total/Piutang</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-medium">{{ $sale->number }}</p>
                                <p class="text-xs text-slate-500">{{ $sale->sale_date->format('d/m/Y') }} · {{ $sale->payment_type === 'credit' ? 'Kredit' : ($sale->payment_method === 'transfer' ? 'Transfer' : 'Cash') }}</p>
                            </td>
                            <td class="px-5 py-4">{{ $sale->customer->name }}</td>
                            <td class="px-5 py-4">
                                <p>Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</p>
                                <p class="text-xs text-orange-700">Piutang Rp {{ number_format((float) $sale->outstanding_amount, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-ui.badge :tone="$sale->status === 'paid' ? 'green' : ($sale->status === 'cancelled' ? 'red' : ($sale->status === 'draft' ? 'gray' : 'orange'))">{{ str($sale->status)->replace('_', ' ')->title() }}</x-ui.badge>
                                    @if (! in_array($sale->status, ['draft', 'cancelled'], true))
                                        <x-ui.badge :tone="$sale->delivery_status === 'complete' ? 'green' : ($sale->delivery_status === 'partial' ? 'orange' : 'blue')">{{ $sale->delivery_status === 'complete' ? 'Terkirim' : ($sale->delivery_status === 'partial' ? 'Kirim Sebagian' : 'Menunggu Gudang') }}</x-ui.badge>
                                    @endif
                                    @if ($sale->return_status !== 'none')
                                        <x-ui.badge tone="orange">Retur {{ $sale->return_status === 'complete' ? 'Penuh' : 'Sebagian' }}</x-ui.badge>
                                    @endif
                                </div>
                                @if ($sale->status === 'cancelled')<p class="mt-1 max-w-xs text-xs text-red-700">{{ $sale->cancellation_reason }}</p>@endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($sale->status === 'draft')
                                        <a href="{{ route('sales.edit', $sale) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('sales.post', $sale) }}">
                                            @csrf
                                            <x-ui.button type="submit">Posting</x-ui.button>
                                        </form>
                                        <details class="text-left">
                                            <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700">Batalkan</summary>
                                            <form method="POST" action="{{ route('sales.cancel', $sale) }}" class="mt-2 grid min-w-64 gap-2 rounded-lg border border-red-200 bg-red-50 p-3">
                                                @csrf
                                                <label for="sale-cancel-reason-{{ $sale->id }}" class="text-xs font-semibold text-red-800">Alasan pembatalan</label>
                                                <textarea id="sale-cancel-reason-{{ $sale->id }}" name="reason" rows="2" required maxlength="2000" class="rounded-lg border border-red-200 px-3 py-2 text-sm"></textarea>
                                                <button type="submit" class="min-h-10 rounded-lg bg-red-700 px-3 text-sm font-semibold text-white">Konfirmasi Batalkan</button>
                                            </form>
                                        </details>
                                    @elseif ($sale->status !== 'cancelled')
                                        <a href="{{ route('sales.invoice', $sale) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview Faktur</a>
                                        @if ($sale->delivery_status !== 'complete' && auth()->user()->hasPermission('sales.deliver'))
                                            <a href="{{ route('sales.deliveries.create', $sale) }}" class="inline-flex min-h-11 items-center rounded-lg border border-blue-300 px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">Buat Surat Jalan</a>
                                        @endif
                                        @if ($sale->return_status !== 'complete')
                                            <a href="{{ route('sales.returns.create', $sale) }}" class="inline-flex min-h-11 items-center rounded-lg border border-orange-300 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50">Retur</a>
                                        @endif
                                        @if ((float) $sale->outstanding_amount > 0)
                                            <a href="{{ route('finance.customer-payment.create', $sale) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand-600 px-3 text-sm font-semibold text-white">Catat Bayar</a>
                                        @endif
                                        @foreach ($sale->returns as $salesReturn)
                                            <a href="{{ route('sales.returns.print', $salesReturn) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-orange-300 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50">Nota {{ $salesReturn->number }}</a>
                                        @endforeach
                                        @foreach ($sale->deliveryNotes as $deliveryNote)
                                            <a href="{{ route('sales.deliveries.print', $deliveryNote) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-blue-300 px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">SJ {{ $deliveryNote->number }}</a>
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada penjualan" description="Buat transaksi penjualan pertama." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $sales->links() }}</div>
</x-app-layout>
