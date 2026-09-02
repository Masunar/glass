<?php

declare(strict_types=1);

use Salvon\Mail\Mailer;
use Salvon\Service\Env;
use Illuminate\Http\Request;
use Salvon\Service\Instance;
use Salvon\Validator\Validator;
use Salvon\Repository\Repository;
use Salvon\Service\FrontendRoute;
use GuzzleHttp\Client as GuzzleClient;
use Salvon\Repository\RepositoryResult;

function permission(BackedEnum|string ...$permissions): string
{
    if (empty($permissions)) {
        throw new InvalidArgumentException('At leas one permission is required');
    }

    return implode('.', map($permissions, function (BackedEnum|string $p) {
        return $p instanceof BackedEnum ? $p->value : $p;
    }));
}

function map(array $items, callable $callback): array
{
    return array_map($callback, $items);
}

function find(array $items, callable $callback): mixed
{
    foreach ($items as $item) {
        if ($callback($item)) {
            return $item;
        }
    }

    return null;
}

function array_items_to_int(array $items): array
{
    return array_map(static fn(mixed $item): int => (int)$item, $items);
}

function array_items_to_string(array $items): array
{
    return array_map(static fn(mixed $item): string => (string)$item, $items);
}

/**
 * @param iterable<mixed, mixed> $data
 */
function each(iterable $data, Closure $closure): void
{
    foreach ($data as $key => $value) {
        $closure($value, $key);
    }
}

function reflection(string|object $item): ?ReflectionClass
{
    return Instance::reflection($item);
}

function instance_of(string|object $item, string $of): bool
{
    return Instance::of($item, $of);
}

function guzzle(array $config = []): GuzzleClient
{
    return new GuzzleClient($config);
}

function is_dev(): bool
{
    return Env::isDev();
}

function repository(Request $request, string $model, int $defaultPerPage = 25, int $maxResults = 500): Repository
{
    return Repository::of($request, $model, $defaultPerPage, $maxResults);
}

function repo_list(Request $request, string $model, array $options = []): RepositoryResult
{
    return Repository::list($request, $model, $options);
}

function validate(Request $request, array $rules, array $messages = []): array
{
    return Validator::handle($request, $rules, $messages);
}

function crud_validate(Request $request, array $rules, array $messages = []): void
{
    Validator::throwable($request, $rules, $messages);
}

function email(array $data = [], ?string $template = null, bool $enqueue = true, ?callable $callback = null): void
{
    Mailer::send($data, $template, $enqueue, $callback);
}

function frontend_route(string $route, array $routeParams = [], array $queryParams = []): string
{
    return FrontendRoute::generatePath($route, ['route' => $routeParams, 'query' => $queryParams]);
}

function join_url(string ...$parts): string
{
    $parts = array_map(static function (string $part): string {
        $part = ltrim($part, '/');
        return rtrim($part, '/');
    }, $parts);

    return implode('/', $parts);
}

function build_url_query(array $args): string
{
    $params = [];
    foreach ($args as $key => $arg) {
        $params[] = sprintf('%s=%s', $key, $arg);
    }

    return rtrim(ltrim(implode('&', $params), '?'), '?');
}

function build_url_with_query(string $url, array $args): string
{
    $params = build_url_query($args);

    return sprintf('%s?%s', rtrim($url), $params);
}

function repeat(int $times, Closure $closure)
{
    for ($i = 0; $i < $times; $i++) {
        $closure($i);
    }
}
