<x-guest-layout title="Masuk">
    <div class="mb-8 lg:hidden">
        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="water" class="size-6" /></span>
    </div>
    <div>
        <p class="text-sm font-semibold text-brand-700">Selamat datang</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Masuk ke sistem</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan akun internal yang diberikan administrator.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 grid gap-5">
        @csrf
        <x-ui.input label="Email" name="email" type="email" required autocomplete="email" autofocus placeholder="nama@perusahaan.com" />
        <x-ui.input label="Password" name="password" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-3 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600">
            Ingat saya di perangkat ini
        </label>
        <x-ui.button type="submit" class="w-full">Masuk</x-ui.button>
    </form>

    <p class="mt-8 text-center text-xs leading-5 text-slate-400">Jika tidak dapat masuk, hubungi administrator perusahaan.</p>
</x-guest-layout>
