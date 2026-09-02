<?php

namespace Salvon\Przelewy24;

final readonly class Url
{
    public static function production(): string
    {
        return 'https://secure.przelewy24.pl';
    }

    public static function sandbox(): string
    {
        return 'https://sandbox.przelewy24.pl';
    }

    public static function gateway(bool $forceSandbox = false): string
    {
        if (Config::isSandbox() || $forceSandbox) {
            return Url::sandbox();
        }

        return Url::production();
    }

    public static function get(string $url, bool $forceSandbox = false): string
    {
        $gateway = self::gateway($forceSandbox);

        return join_url($gateway, $url);
    }

    public static function registerTransaction(bool $forceSandbox = false): string
    {
        return self::get('/api/v1/transaction/register', $forceSandbox);
    }

    public static function verifyTransaction(bool $forceSandbox = false): string
    {
        return self::get('/api/v1/transaction/verify', $forceSandbox);
    }

    public static function paymentForward(string $token, bool $forceSandbox = false): string
    {
        return self::get(
            join_url('/trnRequest', $token),
            $forceSandbox,
        );
    }

    public static function return(string $sessionId): string
    {
        $url = Config::returnUrl();
        return join_url($url, $sessionId);
    }

    public static function transactionInfo(string $sessionsId, bool $forceSandbox = false): string
    {
        return self::get(
            join_url('/api/v1/transaction/by/sessionId/', $sessionsId),
            $forceSandbox,
        );
    }
}
