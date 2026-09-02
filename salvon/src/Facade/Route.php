<?php

namespace Salvon\Facade;

use Closure;
use Salvon\Service\Str;
use Illuminate\Support\Facades\Route as LaravelRoute;

class Route extends LaravelRoute
{
    public static function listRoute(string $controller): void
    {
        self::get('/', [$controller, 'list'])->name('list');
    }

    public static function createRoute(string $controller): void
    {
        self::post('/', [$controller, 'create'])->name('create');
    }

    public static function readRoute(string $controller, string $identifierName = 'entity'): void
    {
        self::get('/{' . $identifierName . '}', [$controller, 'read'])->name('read');
    }

    public static function updateRoute(string $controller, string $identifierName = 'entity'): void
    {
        self::put('/{' . $identifierName . '}', [$controller, 'update'])->name('update');
    }

    public static function deleteRoute(string $controller, string $identifierName = 'entity'): void
    {
        self::delete('/{' . $identifierName . '}', [$controller, 'delete'])->name('delete');
    }

    public static function restoreRoute(string $controller, string $identifierName = 'entity'): void
    {
        self::put('/{' . $identifierName . '}/restore', [$controller, 'restore'])->name('restore');
    }

    public static function crud(string $controller, ?array $withMethods = null, string $identifierName = 'entity'): void
    {
        if (is_null($withMethods)) {
            $withMethods = ['list', 'create', 'read', 'update', 'delete', 'restore'];
        }

        if (in_array('list', $withMethods)) {
            self::listRoute($controller);
        }

        if (in_array('create', $withMethods)) {
            self::createRoute($controller);
        }

        if (in_array('read', $withMethods)) {
            self::readRoute($controller, $identifierName);
        }

        if (in_array('update', $withMethods)) {
            self::updateRoute($controller, $identifierName);
        }

        if (in_array('delete', $withMethods)) {
            self::deleteRoute($controller, $identifierName);
        }

        if (in_array('restore', $withMethods)) {
            self::restoreRoute($controller, $identifierName);
        }
    }

    public static function crudController(string $controller, ?string $prefix = null, ?Closure $callback = null, ?array $withMethods = null): void
    {
        if (is_null($prefix)) {
            $prefix = Str::prefixFromController($controller);
        }

        self::prefix('/' . str_replace('_', '-', $prefix))->name($prefix . '_')->group(static function () use ($controller, $callback, $withMethods): void {
            if (!is_null($callback)) {
                $callback();
            }

            self::crud($controller, $withMethods);
        });
    }

    public static function groupPrefix(string $prefix, Closure $closure): void
    {
        self::prefix($prefix)->name($prefix . '_')->group($closure);
    }

    public static function autoload(string $path, array $ignore = []): void
    {
        foreach (glob($path) as $file) {
            if (in_array(basename($file), $ignore)) {
                continue;
            }

            self::files($file);
        }
    }

    public static function loadApiDir(string $mainDir, array $ignore = ['debug.php']): void
    {
        self::api(static function () use ($mainDir, $ignore): void {
            self::autoload($mainDir . '/api/*.php', $ignore);
        });
    }

    public static function api(callable $callback): void
    {
        self::middleware('api')->prefix('/api')->group(static function () use ($callback): void {
            $callback();
        });
    }

    public static function sanctum(callable $callback): void
    {
        self::middleware('auth:sanctum')->group(static function () use ($callback): void {
            $callback();
        });
    }

    public static function files(string ...$files): void
    {
        foreach ($files as $file) {
            require($file);
        }
    }

    public static function debug(Closure $closure): void
    {
        if (!is_dev()) {
            return;
        }

        Route::any('/debug', $closure);
    }
}
