<x-app-layout title="Buat Penjualan" module="sales" :module-data="config('modules.sales')">
    @php($selectedPaymentType = old('payment_type', 'cash'))
    @php($selectedPaymentMethod = old('payment_method', 'cash'))

    <div class="max-w-4xl">
        <a href="{{ route('sales.index') }}" class="text-sm font-semibold text-brand-700">← Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold">Buat Penjualan</h1>

        <form method="POST" action="{{ route('sales.store') }}" data-payment-form class="mt-7 grid gap-6">
            @csrf
            <section class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <label for="customer_id" class="text-sm font-medium">Pelanggan</label>
                    <select id="customer_id" name="customer_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">Pilih pelanggan</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id') === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <label for="warehouse_id" class="text-sm font-medium">Gudang</label>
                    <select id="warehouse_id" name="warehouse_id" required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.input label="Tanggal Penjualan" name="sale_date" type="date" :value="today()->format('Y-m-d')" required />
                <div class="grid gap-1.5">
                    <label for="payment_type" class="text-sm font-medium">Pembayaran</label>
                    <select id="payment_type" name="payment_type" data-payment-type required class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="cash" @selected($selectedPaymentType === 'cash')>Cash</option>
                        <option value="credit" @selected($selectedPaymentType === 'credit')>Kredit</option>
                    </select>
                    @error('payment_type')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div data-cash-fields @class(['grid gap-1.5', 'hidden' => $selectedPaymentType !== 'cash'])>
                    <label for="payment_method" class="text-sm font-medium">Metode Pembayaran</label>
                    <select id="payment_method" name="payment_method" data-payment-method class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="cash" @selected($selectedPaymentMethod === 'cash')>Cash</option>
                        <option value="transfer" @selected($selectedPaymentMethod === 'transfer')>Transfer</option>
                    </select>
                    @error('payment_method')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div data-transfer-fields @class(['mt-2', 'hidden' => $selectedPaymentMethod !== 'transfer'])>
                        <x-ui.input label="Nama Bank Pengirim" name="sender_bank" maxlength="100" data-sender-bank />
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($cashAccounts as $account)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-xs font-medium text-slate-500">{{ $account->type === 'bank' ? 'Saldo Bank' : 'Saldo Kas Kantor' }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">Rp {{ number_format((float) $account->balance, 0, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div data-credit-fields @class(['grid gap-1.5', 'hidden' => $selectedPaymentType !== 'credit'])>
                    <x-ui.input label="Jatuh Tempo" name="due_date" type="date" data-due-date />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Produk Dijual</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah produk jadi menggunakan satuan karton.</p>
                <div class="mt-5"><x-ui.item-rows :products="$products" /></div>
            </section>

            <div class="flex justify-end"><x-ui.button type="submit">Simpan Draft</x-ui.button></div>
        </form>
    </div>
</x-app-layout>
