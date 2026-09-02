<?php

namespace Salvon\Api;

use GuzzleHttp\Client;

trait HasGuzzleClient
{
    final protected static function guzzleClient(array $config = []): Client
    {
        return guzzle($config);
    }
}
