<?php

namespace Salvon\Bundle\Media\Exception;

use Exception;

class CloudflareCredentialsEmpty extends Exception
{
    protected $message = 'media.cloudflare_credentials_empty';
}
