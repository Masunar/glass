<?php

declare(strict_types=1);

namespace Salvon\UPS;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Salvon\Api\HasGuzzleClient;
use Salvon\Service\Encoder;
use Salvon\UPS\DTO\CredentialsData;
use Salvon\UPS\Exception\UPSException;

final class Auth
{
    use HasGuzzleClient;

    /**
     * @throws UPSException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function accessToken(CredentialsData $credentials): string
    {
        $cacheKey = self::cacheKey($credentials);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $client = self::guzzleClient([
            'auth' => [$credentials->clientId, $credentials->clientSecret],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $response = $client->post(Url::oauthToken($credentials->sandbox), [
            RequestOptions::FORM_PARAMS => ['grant_type' => 'client_credentials'],
        ]);

        if ($response->getStatusCode() >= 400) {
            UPSException::throw('ups_oauth_failed', $response->getStatusCode());
        }

        $body = Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);

        $token = (string) ($body['access_token'] ?? '');
        if ($token === '') {
            UPSException::throw('ups_oauth_missing_token');
        }

        $expiresIn = (int) ($body['expires_in'] ?? Config::tokenCacheTtl());
        $ttl = max(60, min($expiresIn - 60, Config::tokenCacheTtl()));

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public static function forget(CredentialsData $credentials): void
    {
        Cache::forget(self::cacheKey($credentials));
    }

    private static function cacheKey(CredentialsData $credentials): string
    {
        $env = $credentials->sandbox ? 'cie' : 'prod';

        return 'salvon.ups.token.' . $env . '.' . sha1($credentials->clientId);
    }
}
