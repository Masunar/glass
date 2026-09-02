<?php

namespace Salvon\Imagicker\Exception;

use Exception;
use Salvon\Imagicker\Enum\Extension;

class UnsupportedExtension extends Exception
{
    public function __construct()
    {
        parent::__construct(sprintf(
            'Unsupported extension type, see %s to get available extensions list.',
            Extension::class,
        ), 500);
    }
}
