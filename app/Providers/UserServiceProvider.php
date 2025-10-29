<?php

namespace App\Providers;

use App\Services\User\Contract\UserServiceContract;
use App\Services\User\UserService;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserServiceContract::class,
            UserService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        return [UserServiceContract::class];
    }
}
