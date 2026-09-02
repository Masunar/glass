<?php

declare(strict_types=1);

namespace Salvon\SMSApi\Exception;

use Override;
use Exception;
use Salvon\Contract\Exception\StaticThrowable;

class InvalidSmsApiServiceException extends Exception implements StaticThrowable
{
    #[Override] public static function throw(string $message): never
    {
        throw new InvalidSmsApiServiceException(message: $message);
    }
}
