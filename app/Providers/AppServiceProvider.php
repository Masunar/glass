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

        // Dwa obejscia, oba swiadome.
        //
        // Rola nadrzedna omija sprawdzanie w calosci — takze to, co
        // powstanie jutro.
        //
        // Uprawnienie z paczki roli nie istnieje dla spatie: paczki leza
        // w osobnej tabeli, a `hasPermissionTo()` zna wylacznie
        // przypisania roli i uzytkownika. Bez tego przejscia paczka
        // dzialalaby dopiero po recznym zapisaniu roli, czyli bylaby
        // kopia z chwili zapisu zamiast wiazania (U-07) — a rola
        // skonfigurowana samym zasiewem nie dawalaby nic.
        //
        // `null` oddaje decyzje dalej, wiec zwykla droga spatie dziala
        // jak dotad.
        Gate::before(static function (User $user, string $ability): ?true {
            if ($user->isSuperUser()) {
                return true;
            }

            return $user->hasEffectivePermission($ability) ? true : null;
        });
    }
}
