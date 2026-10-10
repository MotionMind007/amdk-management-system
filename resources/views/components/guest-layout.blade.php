@props(['title' => config('app.name')])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="min-h-screen lg:grid lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-brand-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <img src="{{ asset('images/premium-nanwani-robongholo-water-bottles.png') }}" alt="" class="absolute inset-0 size-full object-cover opacity-40" aria-hidden="true">
            <div class="absolute inset-0 bg-brand-950/45" aria-hidden="true"></div>

            <p class="relative z-10 text-sm font-semibold uppercase tracking-[0.16em] text-white/80">PT. AIR MINUM JAYAPURA ROBONGHOLO NANWANI</p>
            <div class="relative z-10 max-w-lg">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-300">Sederhana · Cepat · Terpercaya</p>
                <h1 class="mt-5 text-4xl font-semibold leading-tight">SISTEM OPERASIOAL & MANAGEMENT PABRIK AMDK DALAM SATU APLIKASI.</h1>
                <p class="mt-5 text-base leading-7 text-slate-300">Produksi, stok, penjualan, pembelian, dan keuangan sederhana dengan histori yang jelas.</p>
            </div>
            <p class="relative z-10 text-sm text-slate-300">Akses internal perusahaan</p>
        </section>
        <section class="login-form-panel relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 sm:px-8">
            <div class="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full border-[42px] border-sky-200/30" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-28 -left-28 size-80 rounded-full border-[52px] border-brand-100/50" aria-hidden="true"></div>
            <div class="relative z-10 w-full max-w-md">
                <img src="{{ asset('images/company-logo-transparent.png') }}" alt="PT. Air Minum Jayapura RobongHolo Nanwani" class="mx-auto mb-6 h-auto w-full max-w-sm object-contain sm:mb-8">
                <div class="rounded-3xl border border-white/80 bg-white/80 p-6 shadow-xl shadow-sky-950/5 backdrop-blur-sm sm:p-8">{{ $slot }}</div>
            </div>
        </section>
    </main>
</body>
</html>
