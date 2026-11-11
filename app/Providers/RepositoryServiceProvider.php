<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\UserRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\TeamRepositoryInterface::class,
            \App\Repositories\Eloquent\TeamRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\AttendanceRepositoryInterface::class,
            \App\Repositories\Eloquent\AttendanceRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\AttendanceCorrectionRequestRepositoryInterface::class,
            \App\Repositories\Eloquent\AttendanceCorrectionRequestRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\ShiftRepositoryInterface::class,
            \App\Repositories\Eloquent\ShiftRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\LocationRepositoryInterface::class,
            \App\Repositories\Eloquent\LocationRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
