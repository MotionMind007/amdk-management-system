<x-app-layout title="Pembayaran Supplier" module="purchasing" :module-data="config('modules.purchasing')">
    <div class="max-w-3xl">
        <a href="{{ route('finance.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Catat Pembayaran Supplier</h1>
        <div class="mt-5 rounded-xl bg-orange-50 p-4"><p class="text-sm">{{ $supplierInvoice->purchaseOrder->supplier->name }} · {{ $supplierInvoice->number }} · Faktur {{ $supplierInvoice->supplier_invoice_number }}</p><p class="mt-1 text-xl font-semibold">Sisa Rp {{ number_format((float) $supplierInvoice->outstanding_amount, 0, ',', '.') }}</p></div>

        <form method="POST" action="{{ route('finance.supplier-payment.store', $supplierInvoice) }}" enctype="multipart/form-data" class="mt-5 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Str::uuid()) }}">
            <div class="grid gap-1.5"><label for="cash_account_id" class="text-sm font-medium">Bayar dari</label><select id="cash_account_id" name="cash_account_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }} — Rp {{ number_format((float) $account->balance, 0, ',', '.') }}</option>@endforeach</select>@error('cash_account_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <x-ui.input label="Tanggal Pembayaran" name="payment_date" type="date" :value="old('payment_date', today()->format('Y-m-d'))" required />
            <x-ui.input label="Nominal Pembayaran" name="amount" type="number" min="1" :max="$supplierInvoice->outstanding_amount" step="1" :value="old('amount')" required />
            <div class="grid gap-1.5"><label for="proof" class="text-sm font-medium">Bukti Pembayaran</label><input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><p class="text-xs text-slate-500">Wajib jika dibayar dari rekening bank. Maksimal 5 MB.</p>@error('proof')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div class="grid gap-1.5 sm:col-span-2"><label for="notes" class="text-sm font-medium">Keterangan</label><textarea id="notes" name="notes" rows="3" maxlength="1000" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
            <div class="sm:col-span-2"><x-ui.button type="submit">Simpan Pembayaran</x-ui.button></div>
        </form>

        <section class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Riwayat Pembayaran</h2></div>
            <div class="divide-y divide-slate-100">@forelse ($supplierInvoice->payments->sortByDesc('payment_date') as $payment)<div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">{{ $payment->number }} · Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</p><p class="text-xs text-slate-500">{{ $payment->payment_date->format('d/m/Y') }} · {{ $payment->cashAccount->name }}{{ $payment->notes ? ' · '.$payment->notes : '' }}</p></div>@if ($payment->proof_path)<a href="{{ route('finance.supplier-payment.proof', $payment) }}" class="text-sm font-semibold text-brand-700">Lihat bukti</a>@endif</div>@empty<p class="p-5 text-sm text-slate-500">Belum ada pembayaran.</p>@endforelse</div>
        </section>
    </div>
</x-app-layout>
