<x-app-layout title="Notifikasi">
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-brand-700">Aktivitas Sistem</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">Semua Notifikasi</h1>
                <p class="mt-2 text-sm text-slate-500">Informasi pekerjaan dan transaksi yang relevan dengan role Anda.</p>
            </div>

            @if ($unreadNotificationCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Tandai Semua Dibaca</x-ui.button>
                </form>
            @endif
        </div>

        <div class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-start gap-4 px-5 py-4 text-left transition hover:bg-slate-50 {{ $notification->read_at === null ? 'bg-brand-50/50' : '' }}">
                            <span class="mt-2 size-2.5 shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-brand-600' : 'bg-slate-300' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Notifikasi Sistem' }}</span>
                                    <span class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                </span>
                                <span class="mt-1 block text-sm leading-6 text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="mt-2 block text-xs font-semibold text-brand-700">{{ $notification->data['action_label'] ?? 'Buka' }} →</span>
                            </span>
                        </button>
                    </form>
                @empty
                    <x-ui.empty-state title="Belum ada notifikasi" description="Notifikasi aktivitas yang relevan akan tampil di sini." />
                @endforelse
            </div>
        </div>

        <div class="mt-5">{{ $notifications->links() }}</div>
    </div>
</x-app-layout>
