<?php

namespace App\Providers;

use App\Repositories\Collaborator\CollaboratorRepository;
use App\Repositories\Collaborator\Contract\CollaboratorRepositoryContract;
use App\Repositories\User\Contract\UserRepositoryContract;
use App\Repositories\User\UserRepository;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryContract::class,
            UserRepository::class
        );
        $this->app->bind(
            CollaboratorRepositoryContract::class,
            CollaboratorRepository::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
