<x-app-layout title="Buat Purchase Order" module="purchasing" :module-data="config('modules.purchasing')">
    @php($selectedPaymentType = old('payment_type', 'cash'))

    <div class="max-w-4xl">
        <a href="{{ route('purchasing.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Buat Purchase Order</h1>

        <form method="POST" action="{{ route('purchasing.store') }}" data-payment-form class="mt-7 grid gap-6">
            @csrf
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <label for="supplier_id" class="text-sm font-medium">Supplier</label>
                    <select id="supplier_id" name="supplier_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Pilih supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <label for="warehouse_id" class="text-sm font-medium">Gudang Tujuan</label>
                    <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.input label="Tanggal PO" name="order_date" type="date" :value="today()->format('Y-m-d')" required />
                <x-ui.input label="Perkiraan Tiba" name="expected_date" type="date" />
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
                            <option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }} — Rp {{ number_format((float) $account->balance, 0, ',', '.') }}</option>
                        @endforeach
                    </select>
                    @error('cash_account_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div data-credit-fields @class(['grid gap-1.5', 'hidden' => $selectedPaymentType !== 'credit'])>
                    <x-ui.input label="Jatuh Tempo" name="due_date" type="date" data-due-date />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Barang yang Dipesan</h2>
                <div class="mt-5"><x-ui.item-rows :products="$products" /></div>
            </section>

            <div class="flex justify-end"><x-ui.button type="submit">Simpan Draft PO</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
