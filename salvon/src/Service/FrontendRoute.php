<?php

declare(strict_types=1);

namespace Salvon\Service;

final class FrontendRoute
{
    public static function generatePath(string $route, ?array $params = null): string
    {
        $url = config('frontend_routes')[$route] ?? $route;

        $preparedUrl = $url;
        $routeParams = $params['route'] ?? [];
        $queryParams = $params['query'] ?? [];

        foreach ($routeParams as $param => $value) {
            $preparedUrl = str_replace(":{$param}", $value, $preparedUrl);
        }

        if (count($queryParams) > 0) {
            $preparedUrl = $preparedUrl . '?' . self::objectToQueryParams($queryParams);
        }

        $baseUrl = config('app.frontend.url');
        $baseUrl = rtrim($baseUrl, '/');

        $preparedUrl = ltrim($preparedUrl, '/');

        return sprintf('%s/%s', $baseUrl, $preparedUrl);
    }

    protected static function objectToQueryParams(array $object, ?string $parentKey = null): string
    {
        $parts = [];

        foreach ($object as $key => $value) {
            $fullKey = $parentKey
                ? urlencode($parentKey) . '[' . urlencode($key) . ']'
                : urlencode($key);

            if (is_array($value) && !array_is_list($value)) {
                $parts[] = self::objectToQueryParams($value, $fullKey);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = $fullKey . '[]=' . urlencode($item);
                }
            } else {
                $parts[] = $fullKey . '=' . urlencode($value);
            }
        }

        return implode('&', $parts);
    }
}
