<x-app-layout title="Produksi" module="production" :module-data="config('modules.production')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Operasional</p>
            <h1 class="mt-1 text-2xl font-semibold">Rekap Produksi Harian</h1>
            <p class="mt-2 text-sm text-slate-500">Input hasil produksi per hari; stok berubah saat diposting.</p>
        </div>
        @if (auth()->user()->hasPermission('production.manage'))
            <a href="{{ route('production.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white">+ Buat Rekap</a>
        @endif
    </div>

    @if ($finishedProducts->isNotEmpty())
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
            <div>
                <p class="text-sm font-semibold">Komposisi produk</p>
                <p class="mt-1 text-sm text-slate-500">Komposisi wajib diatur sebelum rekap dapat diposting ke stok.</p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($finishedProducts as $product)
                    <a href="{{ route('production.compositions.edit', $product) }}" class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium {{ $product->compositions_count > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                        <span>{{ $product->name }}</span>
                        <span class="text-xs">{{ $product->compositions_count > 0 ? 'Siap' : 'Belum diatur' }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Nomor/Tanggal</th><th class="px-5 py-3">Hasil</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($productions as $production)
                        @php
                            $missingComposition = $production->items->first(fn ($item) => $item->product->compositions->isEmpty());
                        @endphp
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium">{{ $production->number }}</p><p class="text-xs text-slate-500">{{ $production->production_date->format('d/m/Y') }}</p></td>
                            <td class="px-5 py-4 text-slate-600">@foreach ($production->items as $item)<span class="block">{{ $item->product->name }}: {{ number_format((float) $item->quantity, 0, ',', '.') }}</span>@endforeach</td>
                            <td class="px-5 py-4"><x-ui.badge :tone="$production->status === 'posted' ? 'green' : 'gray'">{{ $production->status === 'posted' ? 'Diposting' : 'Draft' }}</x-ui.badge></td>
                            <td class="px-5 py-4 text-right">
                                @if ($production->status === 'posted')
                                    <span class="text-xs text-slate-400">Final</span>
                                @elseif (auth()->user()->hasPermission('production.manage'))
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('production.edit', $production) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                        @if ($missingComposition)
                                            <a href="{{ route('production.compositions.edit', $missingComposition->product) }}" class="inline-flex min-h-11 items-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-semibold text-amber-800">Atur komposisi dulu</a>
                                        @else
                                            <form method="POST" action="{{ route('production.post', $production) }}">@csrf<x-ui.button type="submit">Posting</x-ui.button></form>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Draft</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6"><x-ui.empty-state title="Belum ada rekap produksi" description="Buat rekap hasil produksi pertama." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $productions->links() }}</div>
</x-app-layout>
