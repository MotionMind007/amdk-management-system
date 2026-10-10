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
                                <x-ui.badge :tone="in_array($po->status, ['completed'], true) ? 'green' : ($po->status === 'draft' ? 'gray' : 'blue')">{{ str($po->status)->replace('_', ' ')->title() }}</x-ui.badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($po->status === 'draft' && auth()->user()->hasPermission('purchasing.approve'))
                                        <form method="POST" action="{{ route('purchasing.approve', $po) }}">
                                            @csrf
                                            <x-ui.button type="submit">Setujui</x-ui.button>
                                        </form>
                                    @elseif (in_array($po->status, ['approved', 'partially_received'], true) && auth()->user()->hasPermission('purchasing.receive'))
                                        <a href="{{ route('purchasing.receive.create', $po) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand-600 px-3 text-sm font-semibold text-white">Terima Barang</a>
                                    @endif

                                    @foreach ($po->goodsReceipts as $receipt)
                                        <a href="{{ route('purchasing.receipts.invoice', $receipt) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview {{ $receipt->number }}</a>
                                        @if ($receipt->proof_path)
                                            <a href="{{ route('purchasing.receipts.proof', $receipt) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unduh Bukti</a>
                                        @endif
                                    @endforeach

                                    @if ((float) $po->outstanding_amount > 0 && auth()->user()->hasPermission('purchasing.manage'))
                                        <a href="{{ route('finance.supplier-payment.create', $po) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold">Bayar</a>
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
