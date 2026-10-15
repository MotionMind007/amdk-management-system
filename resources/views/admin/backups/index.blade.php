<x-app-layout title="Backup Database" module="system" :module-data="config('modules.system')">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">Sistem Admin</p>
            <h1 class="mt-1 text-2xl font-semibold">Backup Database</h1>
            <p class="mt-2 text-sm text-slate-500">Simpan salinan data sebelum deployment atau perubahan besar.</p>
        </div>
        <form method="POST" action="{{ route('admin.backups.store') }}" onsubmit="return confirm('Buat backup database sekarang?')">
            @csrf
            <x-ui.button type="submit">Buat Backup</x-ui.button>
        </form>
    </div>

    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p>File backup disimpan di storage privat dan hanya dapat diunduh oleh pengguna Sistem Admin. Jika disk offsite dikonfigurasi, sistem menyalin backup secara otomatis ke lokasi eksternal.</p>
        <p class="mt-2">Sesudah backup, jalankan <code class="rounded bg-blue-100 px-1.5 py-0.5 font-mono text-xs">php artisan system:verify-backup</code> di server untuk menguji restore tanpa mengubah database aktif.</p>
    </div>

    <div class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
        @forelse($backups as $backup)
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold">{{ $backup['filename'] }}</p>
                    <p class="text-xs text-slate-500">{{ date('d/m/Y H:i', $backup['created_at']) }} · {{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB</p>
                    @if ($backup['verified_at'])
                        <span class="w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Restore terverifikasi {{ date('d/m/Y H:i', $backup['verified_at']) }}</span>
                    @else
                        <span class="w-fit rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Belum diuji restore</span>
                    @endif
                    @if ($backup['offsite_exists'])
                        <span class="w-fit rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Tersalin ke {{ $backup['offsite_disk'] }}</span>
                    @elseif ($backup['offsite_disk'])
                        <span class="w-fit rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Belum tersedia di offsite</span>
                    @else
                        <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Offsite belum dikonfigurasi</span>
                    @endif
                </div>
                <a href="{{ route('admin.backups.download', $backup['filename']) }}" class="text-sm font-semibold text-brand-700">Unduh</a>
            </div>
        @empty
            <div class="p-6">
                <x-ui.empty-state title="Belum ada backup" description="Buat backup pertama sebelum perubahan atau deployment berikutnya." />
            </div>
        @endforelse
    </div>
</x-app-layout>
