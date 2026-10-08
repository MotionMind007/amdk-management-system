@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('water')<path d="M12 2.5S5.5 9.4 5.5 14.5a6.5 6.5 0 0 0 13 0C18.5 9.4 12 2.5 12 2.5Z"/><path d="M9 15.5a3 3 0 0 0 3 3"/>@break
        @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
        @case('arrow-left')<path d="m15 18-6-6 6-6"/>@break
        @case('production')<path d="M3 21h18M5 21V10l5 3V9l5 3V5h4v16"/><path d="M8 17h1m4 0h1m4 0h1"/>@break
        @case('sales')<path d="M3 3v18h18"/><path d="m7 15 4-4 3 2 5-6"/>@break
        @case('purchasing')<path d="M3 4h2l2 11h10l2-7H6"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/>@break
        @case('inventory')<path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/>@break
        @case('finance')<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M16 12h5M7 6V4h10v2"/><circle cx="16" cy="13" r="1"/>@break
        @case('customers')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>@break
        @case('suppliers')<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5"/><path d="M9 9h.01M15 9h.01M9 12h.01M15 12h.01"/>@break
        @case('reports')<path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/>@break
        @case('system')<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.1h-4v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4h-.1v-4H3A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1v-.1h4V3A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.12.38.34.72.6 1 .3.26.65.4 1 .4h.1v4H21a1.7 1.7 0 0 0-1.6.6Z"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('inbox')<path d="M4 4h16v16H4z"/><path d="M4 14h4l2 3h4l2-3h4"/>@break
        @default<circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
