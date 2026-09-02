<?php

namespace Salvon\Imagicker\Exception;

use Exception;

class OverwriteException extends Exception
{
    protected $message = 'You are currently trying to overwrite file you currently editing using save() method, use overwrite() instead.';

    protected $code = 500;
}
