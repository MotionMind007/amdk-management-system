<x-app-layout title="Ubah Password">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('profile.show') }}" class="text-sm font-semibold text-brand-700">← Kembali ke profil</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Ubah Password</h1>
        <p class="mt-2 text-sm text-slate-500">Gunakan password yang kuat dan jangan membagikannya kepada siapa pun.</p>

        <form method="POST" action="{{ route('account.password.update') }}" class="mt-7 grid gap-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @csrf
            @method('PUT')

            <x-ui.input label="Password Saat Ini" name="current_password" type="password" required autocomplete="current-password" />
            <x-ui.input label="Password Baru" name="password" type="password" required autocomplete="new-password" hint="Minimal 8 karakter." />
            <x-ui.input label="Konfirmasi Password Baru" name="password_confirmation" type="password" required autocomplete="new-password" />

            <div class="flex justify-end">
                <x-ui.button type="submit">Simpan Password</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
