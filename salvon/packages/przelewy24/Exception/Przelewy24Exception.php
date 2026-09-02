<?php

declare(strict_types=1);

namespace Salvon\Przelewy24\Exception;

use Override;
use Exception;
use Salvon\Contract\Exception\StaticThrowable;
use Symfony\Component\HttpFoundation\Response;

class Przelewy24Exception extends Exception implements StaticThrowable
{
    /** @throws Przelewy24Exception */
    #[Override]
    public static function throw(string $message, int $code = Response::HTTP_BAD_REQUEST): never
    {
        throw new Przelewy24Exception(message: $message, code: $code);
    }
}
