<x-app-layout title="Transaksi Kas" module="finance" :module-data="config('modules.finance')">
    <div class="max-w-xl">
        <a href="{{ route('finance.index') }}" class="text-sm font-semibold text-brand-700">&larr; Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Catat Pemasukan / Pengeluaran</h1>
        <p class="mt-2 text-sm text-slate-500">Gunakan untuk transaksi kas di luar pembayaran pelanggan dan supplier.</p>
        <form method="POST" action="{{ route('finance.transactions.store') }}" class="mt-6 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <x-ui.input label="Tanggal" name="transaction_date" type="date" :value="old('transaction_date', today()->format('Y-m-d'))" required />
            <div class="grid gap-1.5">
                <label for="cash_account_id" class="text-sm font-medium">Kas / Bank</label>
                <select id="cash_account_id" name="cash_account_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('cash_account_id') == $account->id)>{{ $account->name }} — Rp {{ number_format((float) $account->balance, 0, ',', '.') }}</option>
                    @endforeach
                </select>
                @error('cash_account_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-1.5">
                <label for="direction" class="text-sm font-medium">Jenis Transaksi</label>
                <select id="direction" name="direction" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="in" @selected(old('direction') === 'in')>Pemasukan</option>
                    <option value="out" @selected(old('direction') === 'out')>Pengeluaran</option>
                </select>
                @error('direction')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <x-ui.input label="Nominal" name="amount" type="number" min="1" step="1" :value="old('amount')" required />
            <x-ui.input label="Keterangan" name="description" :value="old('description')" placeholder="Contoh: Biaya listrik pabrik" required />
            <div class="flex justify-end"><x-ui.button type="submit">Simpan Transaksi</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
