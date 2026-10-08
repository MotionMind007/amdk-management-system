@if (session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
        <x-ui.icon name="check" class="mt-0.5 size-5 shrink-0" />
        <p>{{ session('success') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <x-ui.icon name="alert" class="mt-0.5 size-5 shrink-0" />
        <div>
            <p class="font-semibold">Transaksi belum dapat diproses.</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
