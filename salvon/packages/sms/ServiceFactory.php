<?php

declare(strict_types=1);

namespace Salvon\SMSApi;

use Smsapi\Client\Curl\SmsapiHttpClient;
use Smsapi\Client\Service\SmsapiPlService;
use Smsapi\Client\Service\SmsapiComService;

class ServiceFactory
{
    public static function client(): SmsapiHttpClient
    {
        return self::apiClient();
    }

    public static function pl(string $token): SmsapiPlService
    {
        return self::client()->smsapiPlService($token);
    }

    public static function com(string $token): SmsapiComService
    {
        return self::client()->smsapiComService($token);
    }

    private static function apiClient(): SmsapiHttpClient
    {
        return new SmsapiHttpClient();
    }
}
