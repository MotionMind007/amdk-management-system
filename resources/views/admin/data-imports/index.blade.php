<x-app-layout title="Migrasi Data" module="system" :module-data="config('modules.system')">
    <div>
        <p class="text-sm font-semibold text-brand-700">Sistem Admin</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Migrasi Data Awal</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-500">Pindahkan master data lama menggunakan CSV yang dapat diedit di Excel. Sistem hanya menambahkan data baru dan tidak menimpa data yang sudah ada.</p>
    </div>

    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p class="font-semibold">Urutan aman migrasi</p>
        <p class="mt-1">Unduh template, isi tanpa mengubah nama kolom, simpan sebagai CSV, lalu lakukan preview. Data baru tersimpan setelah tombol konfirmasi ditekan. Impor stok awal menghasilkan draft yang wajib diperiksa dan diposting dari menu Stok Awal.</p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($definitions as $type => $definition)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="font-semibold text-slate-900">{{ $definition['label'] }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $definition['description'] }}</p>
                <p class="mt-2 text-xs font-medium text-slate-600">{{ count($definition['columns']) }} kolom sesuai struktur sistem.</p>
                <a href="{{ route('admin.data-imports.template', $type) }}" class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-brand-700">Unduh template CSV</a>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.data-imports.preview') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        <div class="grid gap-5 md:grid-cols-2">
            <div class="grid gap-1.5">
                <label for="type" class="text-sm font-medium text-slate-700">Jenis data <span class="text-red-600">*</span></label>
                <select id="type" name="type" required class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                    <option value="">Pilih jenis data</option>
                    @foreach ($definitions as $type => $definition)
                        <option value="{{ $type }}" @selected(old('type', $preview['type'] ?? '') === $type)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.input label="File CSV" name="file" type="file" accept=".csv,.txt,text/csv" required hint="Maksimal 5 MB dan 1.000 baris. Mendukung pemisah koma, titik koma, atau tab." />
        </div>
        <div class="flex justify-end">
            <x-ui.button type="submit">Periksa & Preview</x-ui.button>
        </div>
    </form>

    @isset($preview)
        <section class="mt-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-700">Preview {{ $preview['label'] }}</p>
                    <h2 class="mt-1 text-xl font-semibold">{{ $originalName }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $preview['valid_count'] }} baris valid · {{ $preview['invalid_count'] }} baris perlu diperbaiki.</p>
                </div>
                @if ($preview['invalid_count'] === 0 && $preview['errors'] === [])
                    <form method="POST" action="{{ route('admin.data-imports.confirm', $token) }}" onsubmit="return confirm('Impor {{ $preview['valid_count'] }} data baru sekarang?')">
                        @csrf
                        <x-ui.button type="submit">Konfirmasi Impor</x-ui.button>
                    </form>
                @endif
            </div>

            @foreach ($preview['errors'] as $error)
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $error }}</div>
            @endforeach

            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Baris</th>
                                @foreach ($definitions[$preview['type']]['columns'] as $column)
                                    <th class="whitespace-nowrap px-4 py-3">{{ str($column)->replace('_', ' ')->title() }}</th>
                                @endforeach
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($preview['rows'] as $row)
                                <tr class="align-top {{ $row['errors'] !== [] ? 'bg-red-50/50' : '' }}">
                                    <td class="px-4 py-3 font-semibold">{{ $row['line'] }}</td>
                                    @foreach ($definitions[$preview['type']]['columns'] as $column)
                                        <td class="max-w-64 whitespace-nowrap px-4 py-3 text-slate-600">
                                            @if (in_array($column, $definitions[$preview['type']]['masked_columns'] ?? [], true))
                                                ••••••••
                                            @else
                                                {{ $row['values'][$column] !== '' ? $row['values'][$column] : '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="min-w-72 px-4 py-3">
                                        @if ($row['errors'] === [])
                                            <x-ui.badge tone="green">Valid</x-ui.badge>
                                        @else
                                            <ul class="list-disc space-y-1 pl-4 text-xs text-red-700">
                                                @foreach ($row['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endisset
</x-app-layout>
