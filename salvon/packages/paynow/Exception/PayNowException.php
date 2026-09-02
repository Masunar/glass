<?php

declare(strict_types=1);

namespace Salvon\Paynow\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class PayNowException extends Exception
{
    /** @throws PayNowException */
    public static function throw(string $message, int $code = Response::HTTP_BAD_REQUEST): never
    {
        throw new PayNowException(message: $message, code: $code);
    }
}
