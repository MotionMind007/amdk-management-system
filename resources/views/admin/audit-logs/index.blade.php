<x-app-layout title="Log Aktivitas" module="system" :module-data="config('modules.system')">
    <div>
        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-brand-700">← Kembali ke user</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Log Aktivitas</h1>
        <p class="mt-2 text-sm text-slate-500">Riwayat lengkap aktivitas seluruh pengguna, termasuk keterangan transaksi dan perubahan data.</p>
    </div>

    <form method="GET" class="mt-7 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <input name="module" value="{{ $module }}" placeholder="Modul, contoh: Finance" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
        <input name="action" value="{{ $action }}" placeholder="Aksi, contoh: ADJUST" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
        <x-ui.button type="submit" variant="secondary">Terapkan Filter</x-ui.button>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Aktivitas</th>
                        <th class="min-w-96 px-5 py-3">Detail</th>
                        <th class="px-5 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium">{{ $log->user?->name ?? 'Sistem' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $log->user?->role?->label() ?? 'Tanpa role' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <x-ui.badge tone="blue">{{ $log->action }}</x-ui.badge>
                                <p class="mt-2 whitespace-nowrap text-xs font-medium text-slate-600">{{ str($log->module)->headline() }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="leading-6 text-slate-700">{{ $log->summary() }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $log->entity_type ? class_basename($log->entity_type).' #'.$log->entity_id : 'Tanpa record terkait' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6"><x-ui.empty-state title="Belum ada aktivitas" description="Aktivitas penting user akan muncul di sini." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
</x-app-layout>
