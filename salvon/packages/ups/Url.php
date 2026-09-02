<?php

declare(strict_types=1);

namespace Salvon\UPS;

final readonly class Url
{
    public static function production(): string
    {
        return 'https://onlinetools.ups.com';
    }

    public static function sandbox(): string
    {
        return 'https://wwwcie.ups.com';
    }

    public static function gateway(bool $sandbox): string
    {
        return $sandbox ? self::sandbox() : self::production();
    }

    public static function get(string $path, bool $sandbox): string
    {
        return join_url(self::gateway($sandbox), $path);
    }

    public static function oauthToken(bool $sandbox): string
    {
        return self::get('/security/v1/oauth/token', $sandbox);
    }

    public static function shipment(bool $sandbox): string
    {
        return self::get('/api/shipments/v2403/ship', $sandbox);
    }

    public static function rate(bool $sandbox): string
    {
        return self::get('/api/rating/v2403/Rate', $sandbox);
    }

    public static function tracking(string $trackingNumber, bool $sandbox): string
    {
        return self::get(join_url('/api/track/v1/details/', $trackingNumber), $sandbox);
    }

    public static function addressValidation(bool $sandbox): string
    {
        return self::get('/api/addressvalidation/v2/1', $sandbox);
    }

    public static function voidShipment(string $shipmentIdentificationNumber, bool $sandbox): string
    {
        return self::get(join_url('/api/shipments/v1/void/cancel/', $shipmentIdentificationNumber), $sandbox);
    }
}
