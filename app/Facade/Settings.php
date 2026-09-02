<?php

declare(strict_types=1);

namespace App\Facade;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Salvon\UPS\DTO\CredentialsData as UpsCredentialsData;

final class Settings
{
    private const CACHE_TTL = 3600;

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember(
            "setting:{$key}",
            self::CACHE_TTL,
            fn () => Setting::query()->find($key),
        );

        if ($setting === null) {
            return $default;
        }

        return $setting->castValue();
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => (string) $value]);
        Cache::forget("setting:{$key}");
    }
}
