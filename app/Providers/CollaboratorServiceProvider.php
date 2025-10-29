<?php

namespace App\Providers;

use App\Services\Collaborator\CollaboratorService;
use App\Services\Collaborator\Contract\CollaboratorServiceContract;
use Illuminate\Support\ServiceProvider;

class CollaboratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CollaboratorServiceContract::class, CollaboratorService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        return [CollaboratorServiceContract::class];
    }
}
