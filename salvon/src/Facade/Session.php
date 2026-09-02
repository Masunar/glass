<?php

declare(strict_types=1);

namespace Salvon\Facade;

use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

final class Session
{
    public static function store(string $key, mixed $value): void
    {
        session()->put($key, $value);
        session()->save();
    }

    public static function get(string $key): mixed
    {
        try {
            return session()->get($key);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
            return null;
        }
    }

    public static function forget(string $key): void
    {
        session()->forget($key);
        session()->save();
    }

    public static function destroy(): void
    {
        session()->flush();
    }
}
