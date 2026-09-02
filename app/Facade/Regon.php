<?php

declare(strict_types=1);

namespace App\Facade;

use Illuminate\Support\Facades\Config;
use Salvon\Regon\Regon as RegonClient;

final class Regon
{
    public static function client(): RegonClient
    {
        $token = Config::get('salvon.regon.token');
        $useTestEnv = Config::get('salvon.regon.use_test_env');

        return new RegonClient($token, $useTestEnv);
    }
}
