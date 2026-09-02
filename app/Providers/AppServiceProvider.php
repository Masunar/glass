<?php

namespace App\Providers;

use App\Models\User;
use App\Listeners\AuthListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, AuthListener::class);

        Gate::before(static function (User $user): ?true {
            return $user->isSuperUser() ? true : null;
        });
    }
}
