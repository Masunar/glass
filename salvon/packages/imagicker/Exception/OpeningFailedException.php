<?php

namespace Salvon\Imagicker\Exception;

use Exception;

class OpeningFailedException extends Exception
{
    public function __construct(string $file, string $imagickMessage)
    {
        parent::__construct(sprintf(
            'Unable to open file: %s, Imagick exception message: %s',
            $file,
            $imagickMessage,
        ), 500);
    }
}
