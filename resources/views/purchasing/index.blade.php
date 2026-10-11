<x-app-layout title="Pembelian" module="purchasing" :module-data="config('modules.purchasing')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Pembelian</p>
            <h1 class="mt-1 text-2xl font-semibold">Purchase Order</h1>
            <p class="mt-2 text-sm text-slate-500">PO tidak menambah stok; gunakan penerimaan barang saat bahan tiba.</p>
        </div>
        @if (auth()->user()->hasPermission('purchasing.manage'))
            <a href="{{ route('purchasing.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white">+ Buat PO</a>
        @endif
    </div>

    <div class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">PO</th>
                        <th class="px-5 py-3">Supplier</th>
                        <th class="px-5 py-3">Total/Hutang</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-medium">{{ $po->number }}</p>
                                <p class="text-xs text-slate-500">{{ $po->order_date->format('d/m/Y') }} · {{ $po->payment_type === 'cash' ? 'Cash' : 'Kredit' }}</p>
                            </td>
                            <td class="px-5 py-4">{{ $po->supplier->name }}</td>
                            <td class="px-5 py-4">
                                <p>Rp {{ number_format((float) $po->total, 0, ',', '.') }}</p>
                                <p class="text-xs text-orange-700">Hutang Rp {{ number_format((float) $po->outstanding_amount, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-ui.badge :tone="$po->status === 'completed' ? 'green' : (in_array($po->status, ['rejected', 'cancelled'], true) ? 'red' : ($po->status === 'draft' ? 'gray' : 'blue'))">{{ str($po->status)->replace('_', ' ')->title() }}</x-ui.badge>
                                    @if ($po->return_status !== 'none')
                                        <x-ui.badge tone="orange">Retur {{ $po->return_status === 'complete' ? 'Penuh' : 'Sebagian' }}</x-ui.badge>
                                    @endif
                                </div>
                                @if ($po->status === 'rejected')<p class="mt-1 max-w-xs text-xs text-red-700">{{ $po->rejection_reason }}</p>@endif
                                @if ($po->status === 'cancelled')<p class="mt-1 max-w-xs text-xs text-red-700">{{ $po->cancellation_reason }}</p>@endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($po->status !== 'cancelled')
                                        <a href="{{ route('purchasing.print', $po) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cetak PO</a>
                                    @endif
                                    @if (in_array($po->status, ['draft', 'rejected'], true) && auth()->user()->hasPermission('purchasing.manage'))
                                        <a href="{{ route('purchasing.edit', $po) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                    @endif
                                    @if ($po->status === 'draft' && auth()->user()->hasPermission('purchasing.approve'))
                                        <form method="POST" action="{{ route('purchasing.approve', $po) }}">
                                            @csrf
                                            <x-ui.button type="submit">Setujui</x-ui.button>
                                        </form>
                                        <details class="text-left">
                                            <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700">Tolak</summary>
                                            <form method="POST" action="{{ route('purchasing.reject', $po) }}" class="mt-2 grid min-w-64 gap-2 rounded-lg border border-red-200 bg-red-50 p-3">
                                                @csrf
                                                <label for="reject-reason-{{ $po->id }}" class="text-xs font-semibold text-red-800">Alasan penolakan</label>
                                                <textarea id="reject-reason-{{ $po->id }}" name="reason" rows="2" required maxlength="2000" class="rounded-lg border border-red-200 px-3 py-2 text-sm"></textarea>
                                                <button type="submit" class="min-h-10 rounded-lg bg-red-700 px-3 text-sm font-semibold text-white">Konfirmasi Tolak</button>
                                            </form>
                                        </details>
                                    @elseif (in_array($po->status, ['approved', 'partially_received'], true) && auth()->user()->hasPermission('purchasing.receive'))
                                        <a href="{{ route('purchasing.receive.create', $po) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand-600 px-3 text-sm font-semibold text-white">Terima Barang</a>
                                    @endif

                                    @foreach ($po->goodsReceipts as $receipt)
                                        <a href="{{ route('purchasing.receipts.invoice', $receipt) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview {{ $receipt->number }}</a>
                                        @if ($receipt->proof_path)
                                            <a href="{{ route('purchasing.receipts.proof', $receipt) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unduh Bukti</a>
                                        @endif
                                        @if (auth()->user()->hasPermission('purchasing.manage'))
                                            <a href="{{ route('purchasing.returns.create', $receipt) }}" class="inline-flex min-h-11 items-center rounded-lg border border-orange-300 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50">Retur {{ $receipt->number }}</a>
                                        @endif
                                        @if ($receipt->supplierInvoice)
                                            <a href="{{ route('purchasing.supplier-invoices.print', $receipt->supplierInvoice) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-violet-300 px-3 text-sm font-semibold text-violet-700 hover:bg-violet-50">Tagihan {{ $receipt->supplierInvoice->number }}</a>
                                            @if ((float) $receipt->supplierInvoice->outstanding_amount > 0 && auth()->user()->hasPermission('finance.manage'))
                                                <a href="{{ route('finance.supplier-payment.create', $receipt->supplierInvoice) }}" class="inline-flex min-h-11 items-center rounded-lg bg-violet-700 px-3 text-sm font-semibold text-white">Bayar Tagihan</a>
                                            @endif
                                        @elseif (auth()->user()->hasPermission('finance.manage'))
                                            <a href="{{ route('purchasing.supplier-invoices.create', $receipt) }}" class="inline-flex min-h-11 items-center rounded-lg border border-violet-300 px-3 text-sm font-semibold text-violet-700 hover:bg-violet-50">Catat Faktur Supplier</a>
                                        @endif
                                    @endforeach

                                    @foreach ($po->purchaseReturns as $purchaseReturn)
                                        <a href="{{ route('purchasing.returns.print', $purchaseReturn) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-orange-300 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50">Nota {{ $purchaseReturn->number }}</a>
                                    @endforeach

                                    @if (in_array($po->status, ['draft', 'rejected', 'approved'], true) && auth()->user()->hasPermission('purchasing.manage'))
                                        <details class="text-left">
                                            <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700">Batalkan</summary>
                                            <form method="POST" action="{{ route('purchasing.cancel', $po) }}" class="mt-2 grid min-w-64 gap-2 rounded-lg border border-red-200 bg-red-50 p-3">
                                                @csrf
                                                <label for="cancel-reason-{{ $po->id }}" class="text-xs font-semibold text-red-800">Alasan pembatalan</label>
                                                <textarea id="cancel-reason-{{ $po->id }}" name="reason" rows="2" required maxlength="2000" class="rounded-lg border border-red-200 px-3 py-2 text-sm"></textarea>
                                                <button type="submit" class="min-h-10 rounded-lg bg-red-700 px-3 text-sm font-semibold text-white">Konfirmasi Batalkan</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada purchase order" description="Buat PO pertama untuk pembelian bahan." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $purchaseOrders->links() }}</div>
</x-app-layout>
