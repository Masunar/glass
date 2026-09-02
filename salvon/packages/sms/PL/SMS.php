<?php

declare(strict_types=1);

namespace Salvon\SMSApi\PL;

use Salvon\SMSApi\Config;
use Smsapi\Client\Feature\Sms\SmsFeature;
use Salvon\SMSApi\ServiceFactory;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;
use Smsapi\Client\Feature\Sms\Data\Sms as SmsData;

final class SMS
{
    public static function send(string $to, string $message, ?string $from = null, ?string $apiToken = null): SmsData
    {
        if (is_null($apiToken)) {
            $apiToken = Config::token();
        }

        $sms = self::service($apiToken);
        $from = self::resolveFrom($from);
        $smsBag = SendSmsBag::withMessage($to, $message);

        if (!is_null($from)) {
            $smsBag->from = $from;
        }

        return $sms->sendSms($smsBag);
    }

    private static function resolveFrom(?string $from): ?string
    {
        if (!is_null($from)) {
            return $from;
        }

        $defaultFrom = Config::defaultFrom();

        if (!is_null($defaultFrom)) {
            return $defaultFrom;
        }

        return null;
    }

    private static function service(string $apiToken): SmsFeature
    {
        return ServiceFactory::pl($apiToken)->smsFeature();
    }
}
