<?php

namespace App\Providers;

use App\Models\TaskMessage;
use App\Observers\TaskMessageObserver;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TaskMessage::observe(TaskMessageObserver::class);

        RedirectIfAuthenticated::redirectUsing(function ($request) {
            return route('app.dashboard'); // or '/app/dashboard'
        });
    }
}
