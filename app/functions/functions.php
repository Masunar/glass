<?php

declare(strict_types=1);

use App\Models\User;
use Salvon\Facade\Auth;
use Salvon\Facade\Session;

function user(?string $guard = null): User
{
    if ($guard) {
        /** @var User */
        return Auth::guardUser($guard);
    }

    /** @var User */
    return Auth::user();
}

function logout(?string $guard = null): void
{
    if ($guard) {
        Auth::guard($guard)->logout();
        Session::destroy();
    }

    Auth::logout();
    Session::destroy();
}

function authenticated(): bool
{
    return Auth::authenticated();
}
