<?php

declare(strict_types=1);

namespace Salvon\Facade;

use Illuminate\Contracts\Auth\StatefulGuard;
use Throwable;
use Salvon\Model\User as SalvonUser;
use Illuminate\Support\Facades\Auth as LaravelAuth;

/**
 * Authenticated user handling
 * Throws Exception at non-logged in user
 */
final class Auth
{
    public static function authenticated(): bool
    {
        return self::userOrNull() instanceof SalvonUser;
    }

    /**
     * @throws Throwable
     */
    public static function user(): SalvonUser
    {
        $user = self::userOrNull();

        if (!$user) {
            Error::unauthorized();
        }

        return $user;
    }

    /**
     * @throws Throwable
     */
    public static function guardUser(string $guard): SalvonUser
    {
        /** @var SalvonUser|null $user */
        $user = LaravelAuth::guard($guard)->user();

        if (!$user) {
            Error::unauthorized();
        }

        return $user;
    }

    /**
     * @throws Throwable
     */
    public static function userId(): int
    {
        return self::user()->id;
    }

    public static function attempt(array $credentials = [], bool $remember = false): bool
    {
        return LaravelAuth::attempt($credentials, $remember);
    }

    public static function login(SalvonUser|int $user, bool $remember = false): void
    {
        if (!is_int($user)) {
            $user = $user->id;
        }

        LaravelAuth::loginUsingId($user, $remember);
    }

    public static function logout(): void
    {
        LaravelAuth::logout();
    }

    public static function userOrNull(): ?SalvonUser
    {
        /** @var SalvonUser|null $user */
        $user = LaravelAuth::user();
        return $user;
    }

    public static function validate(array $credentials): bool
    {
        return LaravelAuth::validate($credentials);
    }

    public static function once(array $credentials): bool
    {
        return LaravelAuth::once($credentials);
    }

    public static function guard(string $guard): StatefulGuard
    {
        return LaravelAuth::guard($guard);
    }
}
