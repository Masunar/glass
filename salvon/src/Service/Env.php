<?php

declare(strict_types=1);

namespace Salvon\Service;

use Illuminate\Support\Facades\App;

final class Env
{
    public static function isDev(): bool
    {
        return (bool) App::environment('local', 'development', 'dev');
    }

    public static function isProduction(): bool
    {
        return (bool) App::environment('prod', 'production');
    }

    public static function isTest(): bool
    {
        return (bool) App::environment('test', 'testing');
    }
}
