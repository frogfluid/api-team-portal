<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        WorkSchedule::class => WorkSchedulePolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
