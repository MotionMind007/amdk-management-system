@php($isEdit = $purchaseOrder->exists)
<x-app-layout :title="$isEdit ? 'Edit Purchase Order' : 'Buat Purchase Order'" module="purchasing" :module-data="config('modules.purchasing')">
    @php($selectedPaymentType = old('payment_type', $purchaseOrder->payment_type ?? 'cash'))

    <div class="max-w-4xl">
        <a href="{{ route('purchasing.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">{{ $isEdit ? 'Edit '.$purchaseOrder->number : 'Buat Purchase Order' }}</h1>
        @if ($purchaseOrder->status === 'rejected')
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">PO ditolak dan perlu direvisi.</p>
                <p class="mt-1">{{ $purchaseOrder->rejection_reason }}</p>
            </div>
        @endif

        <form method="POST" action="{{ $isEdit ? route('purchasing.update', $purchaseOrder) : route('purchasing.store') }}" data-payment-form class="mt-7 grid gap-6">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <label for="supplier_id" class="text-sm font-medium">Supplier</label>
                    <select id="supplier_id" name="supplier_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Pilih supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $purchaseOrder->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <label for="warehouse_id" class="text-sm font-medium">Gudang Tujuan</label>
                    <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $purchaseOrder->warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.input label="Tanggal PO" name="order_date" type="date" :value="$purchaseOrder->order_date?->format('Y-m-d') ?? today()->format('Y-m-d')" required />
                <x-ui.input label="Perkiraan Tiba" name="expected_date" type="date" :value="$purchaseOrder->expected_date?->format('Y-m-d')" />
                <div class="grid gap-1.5">
                    <label for="payment_type" class="text-sm font-medium">Pembayaran</label>
                    <select id="payment_type" name="payment_type" data-payment-type required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="cash" @selected($selectedPaymentType === 'cash')>Cash</option>
                        <option value="credit" @selected($selectedPaymentType === 'credit')>Kredit</option>
                    </select>
                    @error('payment_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div data-cash-fields @class(['grid gap-1.5', 'hidden' => $selectedPaymentType !== 'cash'])>
                    <label for="cash_account_id" class="text-sm font-medium">Bayar dari Kas/Bank</label>
                    <select id="cash_account_id" name="cash_account_id" data-cash-account class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Pilih akun kas atau bank</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('cash_account_id', $purchaseOrder->cash_account_id) === $account->id)>{{ $account->name }} — Rp {{ number_format((float) $account->balance, 0, ',', '.') }}</option>
                        @endforeach
                    </select>
                    @error('cash_account_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div data-credit-fields @class(['grid gap-1.5', 'hidden' => $selectedPaymentType !== 'credit'])>
                    <x-ui.input label="Jatuh Tempo" name="due_date" type="date" :value="$purchaseOrder->due_date?->format('Y-m-d')" data-due-date />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="notes" class="text-sm font-medium text-slate-700">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                    @error('notes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Barang yang Dipesan</h2>
                <div class="mt-5"><x-ui.item-rows :products="$products" :items="$purchaseOrder->items ?? []" /></div>
            </section>

            <div class="flex justify-end"><x-ui.button type="submit">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft PO' }}</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
