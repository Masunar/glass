<?php

declare(strict_types=1);

namespace Salvon\SMSApi;

final class Config
{
    public static function token(string $service = 'pl'): string
    {
        if ($service === 'pl') {
            return config('salvon.sms.smsapi.token_pl', '');
        }

        return config('salvon.sms.smsapi.token_com', '');
    }

    public static function defaultFrom(): ?string
    {
        return config('salvon.sms.smsapi.default_from');
    }
}
