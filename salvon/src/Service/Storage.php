<?php

declare(strict_types=1);

namespace Salvon\Service;

use InvalidArgumentException;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage as LaravelStorage;

final class Storage extends LaravelStorage
{
    public static function driver(): string
    {
        return Config::get('filesystems.default');
    }

    public static function root(): string
    {
        $driver = self::driver();

        $path = Config::get(sprintf('filesystems.disks.%s.root', $driver));

        if (empty($path)) {
            throw new InvalidArgumentException(sprintf('Driver %s does not support local root path.', $path));
        }

        return Config::get(sprintf('filesystems.disks.%s.root', $driver));
    }

    public static function copyBetween(string $filePath, Filesystem $from, Filesystem $to, ?string $toPath = null): bool
    {
        if (is_null($toPath)) {
            $toPath = $filePath;
        }

        $file = $from->get($filePath);

        return $to->put($toPath, $file);
    }

    public static function moveBetween(string $filePath, Filesystem $from, Filesystem $to, ?string $toPath = null): bool
    {
        $copied = self::copyBetween($filePath, $from, $to, $toPath);

        if ($copied) {
            $from->delete($filePath);
        }

        return $copied;
    }

    public static function disk(): Filesystem
    {
        return parent::disk(self::driver());
    }

    public static function local(): Filesystem
    {
        return parent::disk('local');
    }

    public static function temp(): Filesystem
    {
        return parent::disk('temp');
    }

    public static function media(): Filesystem
    {
        return parent::disk('media');
    }

    public static function static(): Filesystem
    {
        return parent::disk('static');
    }
}
