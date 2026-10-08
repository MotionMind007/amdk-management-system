@props(['title', 'module' => null, 'moduleData' => null])

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
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-screen-2xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    @if ($module)
                        <button type="button" data-menu-toggle="module-navigation" aria-expanded="false" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden">
                            <span class="sr-only">Buka menu</span>
                            <x-ui.icon name="menu" class="size-6" />
                        </button>
                    @endif
                    <a href="{{ route('modules.index') }}" class="flex min-w-0 items-center gap-3">
                        <img src="{{ asset('images/company-logo-transparent.png') }}" alt="PT. Air Minum Jayapura RobongHolo Nanwani" class="h-9 w-auto max-w-36 object-contain sm:h-10 sm:max-w-44">
                        <span class="hidden sm:block">
                            <span class="block text-xs font-medium text-slate-500">{{ $moduleData['label'] ?? 'Pusat Modul' }}</span>
                        </span>
                    </a>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Keluar</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-screen-2xl">
            @if ($module)
                <aside id="module-navigation" class="fixed inset-x-0 top-16 z-20 hidden border-b border-slate-200 bg-white p-4 shadow-lg lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)] lg:w-64 lg:shrink-0 lg:border-r lg:border-b-0 lg:shadow-none">
                    <a href="{{ route('modules.index') }}" class="mb-5 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        <x-ui.icon name="arrow-left" class="size-4" /> Kembali ke Modul
                    </a>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $moduleData['label'] }}</p>
                    <nav class="mt-3 flex flex-col gap-1">
                        @foreach ($moduleData['navigation'] as $item)
                            @continue(isset($item['permission']) && ! auth()->user()->hasPermission($item['permission']))
                            <a href="{{ route($item['route']) }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs($item['active']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </aside>
            @endif

            <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <x-ui.flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
