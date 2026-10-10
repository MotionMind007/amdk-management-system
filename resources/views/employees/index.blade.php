<x-app-layout title="Karyawan" module="employees" :module-data="config('modules.employees')">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Data Karyawan</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola identitas, jabatan, departemen, dan status karyawan.</p>
        </div>
        <a href="{{ route('employees.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">+ Tambah Karyawan</a>
    </div>

    <form method="GET" class="mt-7 flex gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="search" value="{{ $search }}" placeholder="Cari kode, nama, jabatan, atau departemen..." class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
    </form>

    <div class="mt-4 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Kode</th>
                    <th class="px-5 py-3">Karyawan</th>
                    <th class="px-5 py-3">Jabatan</th>
                    <th class="px-5 py-3">Tanggal Masuk</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-5 py-4 font-medium text-slate-700">{{ $employee->employee_code }}</td>
                        <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $employee->name }}</p><p class="text-xs text-slate-500">{{ $employee->phone ?: 'Tanpa telepon' }}</p></td>
                        <td class="px-5 py-4"><p class="text-slate-700">{{ $employee->position }}</p><p class="text-xs text-slate-500">{{ $employee->department }}</p></td>
                        <td class="px-5 py-4 text-slate-600">{{ $employee->join_date?->format('d/m/Y') ?? 'Belum diisi' }}</td>
                        <td class="px-5 py-4"><x-ui.badge :tone="$employee->status === 'active' ? 'green' : 'gray'">{{ $employee->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge></td>
                        <td class="px-5 py-4 text-right">
                            @if ($employee->user === null || auth()->user()->role->canAssign($employee->user->role))
                                <a href="{{ route('employees.edit', $employee) }}" class="font-semibold text-brand-700">Edit</a>
                            @else
                                <span class="text-xs text-slate-400">Dilindungi</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6"><x-ui.empty-state title="Belum ada karyawan" description="Tambahkan data karyawan pertama." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 grid gap-3 md:hidden">
        @forelse ($employees as $employee)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-medium text-slate-500">{{ $employee->employee_code }}</p><h2 class="mt-1 font-semibold text-slate-900">{{ $employee->name }}</h2></div>
                    <x-ui.badge :tone="$employee->status === 'active' ? 'green' : 'gray'">{{ $employee->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Jabatan</dt><dd class="mt-1 text-slate-700">{{ $employee->position }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Departemen</dt><dd class="mt-1 text-slate-700">{{ $employee->department }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Tanggal masuk</dt><dd class="mt-1 text-slate-700">{{ $employee->join_date?->format('d/m/Y') ?? 'Belum diisi' }}</dd></div>
                </dl>
                @if ($employee->user === null || auth()->user()->role->canAssign($employee->user->role))
                    <a href="{{ route('employees.edit', $employee) }}" class="mt-4 block rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-semibold text-slate-700">Edit data</a>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-center text-xs font-medium text-slate-400">Akun dilindungi</p>
                @endif
            </article>
        @empty
            <x-ui.empty-state title="Belum ada karyawan" description="Tambahkan data karyawan pertama." />
        @endforelse
    </div>

    <div class="mt-5">{{ $employees->links() }}</div>
</x-app-layout>
