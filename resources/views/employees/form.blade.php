<x-app-layout :title="$employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan'" module="employees" :module-data="config('modules.employees')">
    <div class="max-w-3xl">
        <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-brand-700">← Kembali ke daftar</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">{{ $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan' }}</h1>

        <form method="POST" enctype="multipart/form-data" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="mt-7 grid gap-6">
            @csrf
            @if ($employee->exists)
                @method('PUT')
            @endif

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Informasi Pribadi</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Kode Karyawan" name="employee_code" :value="$employee->employee_code" required placeholder="EMP-001" />
                    <x-ui.input label="Nama Karyawan" name="name" :value="$employee->name" required />
                    <x-ui.input label="Telepon" name="phone" :value="$employee->phone" />
                    <x-ui.input label="Email Login" name="email" type="email" :value="$employee->email" required autocomplete="username" hint="Email ini digunakan karyawan untuk masuk ke sistem." />
                    <div class="grid gap-3 sm:col-span-2">
                        <label for="photo" class="text-sm font-medium text-slate-700">Foto Karyawan <span class="font-normal text-slate-400">(opsional)</span></label>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            @if ($employee->photo_path)
                                <img src="{{ asset('storage/'.$employee->photo_path) }}" alt="Foto {{ $employee->name }}" class="size-20 rounded-xl border border-slate-200 object-cover">
                            @endif
                            <div class="min-w-0 flex-1">
                                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                                <p class="mt-1.5 text-xs text-slate-500">Format JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                                @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <label for="address" class="text-sm font-medium text-slate-700">Alamat</label>
                        <textarea id="address" name="address" rows="3" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('address', $employee->address) }}</textarea>
                        @error('address')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Akun Login</h2>
                <p class="mt-1 text-sm text-slate-500">Akun sistem dibuat otomatis bersama data karyawan.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <label for="role" class="text-sm font-medium text-slate-700">Role Login <span class="text-red-600">*</span></label>
                        <select id="role" name="role" required class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $employee->user?->role?->value ?? \App\UserRole::Sales->value) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div></div>
                    @php($requiresPassword = ! $employee->exists || $employee->user === null)
                    <x-ui.input :label="$requiresPassword ? 'Password Awal' : 'Password Baru'" name="password" type="password" :required="$requiresPassword" autocomplete="new-password" :hint="$requiresPassword ? 'Minimal 8 karakter.' : 'Kosongkan jika tidak ingin mengganti password.'" />
                    <x-ui.input label="Konfirmasi Password" name="password_confirmation" type="password" :required="$requiresPassword" autocomplete="new-password" />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Informasi Pekerjaan</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Jabatan" name="position" :value="$employee->position" required />
                    <x-ui.input label="Departemen" name="department" :value="$employee->department" required />
                    <x-ui.input label="Tanggal Masuk" name="join_date" type="date" :value="$employee->join_date?->format('Y-m-d')" />
                    <div class="grid gap-1.5">
                        <label for="status" class="text-sm font-medium text-slate-700">Status</label>
                        <select id="status" name="status" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                            <option value="active" @selected(old('status', $employee->status ?? 'active') === 'active')>Aktif</option>
                            <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Tidak aktif</option>
                        </select>
                        @error('status')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('employees.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Batal</a>
                <x-ui.button type="submit">{{ $employee->exists ? 'Simpan Perubahan' : 'Simpan Karyawan' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
