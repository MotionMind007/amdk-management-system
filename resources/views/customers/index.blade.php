<x-app-layout title="Pelanggan" module="customers" :module-data="config('modules.customers')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold text-brand-700">Master Data</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Pelanggan</h1><p class="mt-2 text-sm text-slate-500">Kelola identitas, termin, dan batas kredit pelanggan.</p></div>
        @if (auth()->user()->hasPermission('customers.manage'))<a href="{{ route('customers.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">+ Tambah Pelanggan</a>@endif
    </div>

    <form method="GET" class="mt-7 flex gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="search" value="{{ $search }}" placeholder="Cari kode, nama, atau telepon..." class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
    </form>

    <div class="mt-4 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3">Termin</th><th class="px-5 py-3">Batas Kredit</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $customer)
                    <tr><td class="px-5 py-4 font-medium text-slate-700">{{ $customer->customer_code }}</td><td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $customer->name }}</p><p class="text-xs text-slate-500">{{ $customer->phone ?: 'Tanpa telepon' }}</p></td><td class="px-5 py-4 text-slate-600">{{ $customer->payment_term }} hari</td><td class="px-5 py-4 text-slate-600">Rp {{ number_format((float) $customer->credit_limit, 0, ',', '.') }}</td><td class="px-5 py-4"><x-ui.badge :tone="$customer->status === 'active' ? 'green' : 'gray'">{{ $customer->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge></td><td class="px-5 py-4 text-right">@if (auth()->user()->hasPermission('customers.manage'))<a href="{{ route('customers.edit', $customer) }}" class="font-semibold text-brand-700">Edit</a>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="p-6"><x-ui.empty-state title="Belum ada pelanggan" description="Tambahkan pelanggan pertama untuk mulai mencatat penjualan." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 grid gap-3 md:hidden">
        @forelse ($customers as $customer)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-medium text-slate-500">{{ $customer->customer_code }}</p><h2 class="mt-1 font-semibold text-slate-900">{{ $customer->name }}</h2></div><x-ui.badge :tone="$customer->status === 'active' ? 'green' : 'gray'">{{ $customer->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge></div><dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-slate-500">Termin</dt><dd class="mt-1 text-slate-700">{{ $customer->payment_term }} hari</dd></div><div><dt class="text-xs text-slate-500">Batas kredit</dt><dd class="mt-1 text-slate-700">Rp {{ number_format((float) $customer->credit_limit, 0, ',', '.') }}</dd></div></dl>@if (auth()->user()->hasPermission('customers.manage'))<a href="{{ route('customers.edit', $customer) }}" class="mt-4 block rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-semibold text-slate-700">Edit data</a>@endif</article>
        @empty
            <x-ui.empty-state title="Belum ada pelanggan" description="Tambahkan pelanggan pertama untuk mulai mencatat penjualan." />
        @endforelse
    </div>
    <div class="mt-5">{{ $customers->links() }}</div>
</x-app-layout>
