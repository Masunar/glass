<?php

declare(strict_types=1);

namespace Salvon\Middleware;

use Closure;
use TypeError;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->headers->get('Locale');
        if (!is_null($locale)) {
            $locale = $this->determineLocale($locale);
            App::setLocale($locale->value);
        }

        return $next($request);
    }

    private function determineLocale(string $locale): BackedEnum
    {
        $enumClass = Config::get('salvon.framework.locale_enum');
        $defaultLocale = Config::get('salvon.framework.default_locale');

        if (!reflection($enumClass)->isEnum()) {
            throw new TypeError('LOCALE_ENUM option must be instance of Enum.');
        }

        return $enumClass::tryFrom($locale) ?? $defaultLocale;
    }
}
