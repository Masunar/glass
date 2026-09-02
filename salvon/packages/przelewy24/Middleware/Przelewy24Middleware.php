<?php

namespace Salvon\Przelewy24\Middleware;

use Closure;
use Salvon\Facade\Error;
use Illuminate\Http\Request;
use Salvon\Przelewy24\Config;
use Illuminate\Support\Facades\Config as LaravelConfig;

class Przelewy24Middleware
{
    public function handle(Request $request, Closure $next)
    {
        if (LaravelConfig::get('app.env') === 'local') {
            return $next($request);
        }

        $ips = Config::ips();

        if (!in_array($request->ip(), $ips, true)) {
            Error::unauthorized('Unauthorized IP address');
        }

        return $next($request);
    }
}
