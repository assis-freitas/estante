<?php

namespace App\Providers;

use App\Policies\CopyPolicy;
use Estante\Shelf\Domain\Copy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Copy::class, CopyPolicy::class);
    }
}
