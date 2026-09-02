<?php

declare(strict_types=1);

namespace Salvon\Service;

use ReflectionClass;
use ReflectionException;

final class Instance
{
    public static function of(string|object $item, string $of): bool
    {
        if ($item instanceof $of) {
            return true;
        }

        if (!is_string($item)) {
            $item = $item::class;
        }

        if (!class_exists($item)) {
            return false;
        }

        if (!class_exists($of) && !interface_exists($of)) {
            return false;
        }

        $reflectionClass = self::reflection($item);

        if (!$reflectionClass instanceof ReflectionClass) {
            return false;
        }

        if (interface_exists($of)) {
            return $reflectionClass->implementsInterface($of);
        }

        if (class_exists($of)) {
            return $reflectionClass->isSubclassOf($of);
        }

        return false;
    }

    public static function reflection(string|object $instance): ?ReflectionClass
    {
        try {
            return new ReflectionClass($instance);
        } catch (ReflectionException) {
            return null;
        }
    }

    public static function directory(string|object $instance): string
    {
        return dirname(self::reflection($instance)->getFileName());
    }

    public static function targetClassName(string|object $instance): string
    {
        return self::reflection($instance)->getName();
    }

    public static function isEnum(string|object $instance): ?bool
    {
        return self::reflection($instance)?->isEnum() ?? false;
    }
}
