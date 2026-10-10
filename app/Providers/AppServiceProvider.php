<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
