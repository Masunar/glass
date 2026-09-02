<?php

declare(strict_types=1);

namespace Salvon\UPS\Exception;

use Override;
use Exception;
use Salvon\Contract\Exception\StaticThrowable;
use Symfony\Component\HttpFoundation\Response;

class UPSException extends Exception implements StaticThrowable
{
    /** @throws UPSException */
    #[Override]
    public static function throw(string $message, int $code = Response::HTTP_BAD_REQUEST): never
    {
        throw new UPSException(message: $message, code: $code);
    }
}
