<x-app-layout title="Umur Piutang & Hutang" module="finance" :module-data="config('modules.finance')">
    <div><p class="text-sm font-semibold text-brand-700">Kontrol Jatuh Tempo</p><h1 class="mt-1 text-2xl font-semibold">Umur Piutang & Hutang</h1><p class="mt-2 text-sm text-slate-500">Outstanding dikelompokkan berdasarkan keterlambatan per hari ini.</p></div>
    <div class="mt-7 grid gap-6 xl:grid-cols-2">
        @foreach ([['Piutang Pelanggan', $receivableBuckets, $receivables, 'customer'], ['Hutang Supplier', $payableBuckets, $payables, 'supplier']] as [$title, $buckets, $documents, $partyType])
            <section><h2 class="font-semibold">{{ $title }}</h2><div class="mt-3 grid grid-cols-2 gap-3">@foreach($buckets as $label => $amount)<div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 font-semibold">Rp {{ number_format($amount, 0, ',', '.') }}</p></div>@endforeach</div>
                <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">@forelse($documents as $document)<div class="flex items-center justify-between gap-4 p-4"><div><p class="text-sm font-semibold">{{ $partyType === 'customer' ? $document->customer->name : $document->purchaseOrder->supplier->name }}</p><p class="text-xs text-slate-500">{{ $document->number }} · jatuh tempo {{ $document->due_date?->format('d/m/Y') ?? '-' }}</p></div><p class="shrink-0 text-sm font-semibold text-orange-700">Rp {{ number_format((float) $document->outstanding_amount, 0, ',', '.') }}</p></div>@empty<div class="p-5 text-sm text-slate-500">Tidak ada saldo outstanding.</div>@endforelse</div>
            </section>
        @endforeach
    </div>
</x-app-layout>
