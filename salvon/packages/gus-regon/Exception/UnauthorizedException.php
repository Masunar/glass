<?php

declare(strict_types=1);

namespace Salvon\Regon\Exception;

use Exception;

class UnauthorizedException extends Exception {
    /** @var int $code */
    protected $code = 401;

    /** @var string $message */
    protected $message = 'Unauthorized.';
}
