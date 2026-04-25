<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\AiEvaluation;
use App\Models\JobScope;
use App\Models\RemoteWorkRequest;
use App\Models\WorkSchedule;
use App\Policies\AiEvaluationPolicy;
use App\Policies\JobScopePolicy;
use App\Policies\RemoteWorkRequestPolicy;
use App\Policies\WorkSchedulePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        WorkSchedule::class => WorkSchedulePolicy::class,
        RemoteWorkRequest::class => RemoteWorkRequestPolicy::class,
        AiEvaluation::class => AiEvaluationPolicy::class,
        JobScope::class => JobScopePolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
