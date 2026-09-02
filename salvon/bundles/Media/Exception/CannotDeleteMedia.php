<?php

namespace Salvon\Bundle\Media\Exception;

class CannotDeleteMedia extends \Exception
{
    protected $message = 'media.cannot_delete_media';
    protected $code = 400;
}
