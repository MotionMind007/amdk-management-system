<?php

namespace App\Providers;

use App\Services\SystemHealthService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(12));

        Event::listen(DiagnosingHealth::class, function (): void {
            $health = app(SystemHealthService::class)->check();

            if (! $health['healthy']) {
                throw new \RuntimeException('Pemeriksaan database atau storage gagal.');
            }
        });

        View::composer('components.app-layout', function (ViewContract $view): void {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            $view->with([
                'headerNotifications' => $user->notifications()->limit(5)->get(),
                'unreadNotificationCount' => $user->unreadNotifications()->count(),
            ]);
        });
    }
}
