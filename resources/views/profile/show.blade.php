<x-app-layout title="Profil Karyawan">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('modules.index') }}" class="text-sm font-semibold text-brand-700">← Kembali ke modul</a>

        <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-gradient-to-r from-brand-700 to-sky-500 px-5 py-8 sm:px-8"></div>
            <div class="px-5 pb-6 sm:px-8 sm:pb-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex min-w-0 items-end gap-4">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Foto {{ $user->name }}" class="-mt-10 size-24 shrink-0 rounded-2xl border-4 border-white bg-white object-cover shadow-sm">
                        @else
                            <div class="-mt-10 flex size-24 shrink-0 items-center justify-center rounded-2xl border-4 border-white bg-brand-50 text-3xl font-semibold text-brand-700 shadow-sm">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                        @endif
                        <div class="min-w-0 pb-1">
                            <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">{{ $employee?->name ?? $user->name }}</h1>
                            <p class="mt-1 text-sm text-slate-500">{{ $employee?->position ?? $user->role->label() }}</p>
                        </div>
                    </div>
                    <a href="{{ route('account.password.edit') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">Ganti Password</a>
                </div>

                <dl class="mt-7 grid gap-4 border-t border-slate-100 pt-6 sm:grid-cols-2">
                    <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Email akun</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $user->email }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Role sistem</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $user->role->label() }}</dd></div>
                    @if ($employee)
                        <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Kode karyawan</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $employee->employee_code }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Departemen</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $employee->department }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Telepon</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $employee->phone ?: 'Belum diisi' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Tanggal masuk</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $employee->join_date?->format('d/m/Y') ?? 'Belum diisi' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Status</dt><dd class="mt-1"><x-ui.badge :tone="$employee->status === 'active' ? 'green' : 'gray'">{{ $employee->status === 'active' ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge></dd></div>
                    @else
                        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800 sm:col-span-2">Data master karyawan belum terhubung. Gunakan email <strong>{{ $user->email }}</strong> pada data karyawan agar informasi pekerjaan dan foto tampil di profil ini.</div>
                    @endif
                </dl>
            </div>
        </section>
    </div>
</x-app-layout>
