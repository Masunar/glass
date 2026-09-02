<?php

declare(strict_types=1);

namespace Salvon\Przelewy24;

use Salvon\Service\Encoder;
use GuzzleHttp\RequestOptions;
use Salvon\Api\HasGuzzleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Response;
use Salvon\Przelewy24\DTO\PaymentData;
use Salvon\Przelewy24\DTO\CredentialsData;
use Salvon\Przelewy24\DTO\VerificationData;
use Salvon\Przelewy24\Exception\Przelewy24Exception;

final class Przelewy24
{
    use HasGuzzleClient;

    /**
     * @throws GuzzleException
     * @throws Przelewy24Exception
     */
    public static function registerTransaction(
        PaymentData     $payment,
        bool            $forceSandbox = false,
        CredentialsData $credentials = null,
        ?string         $validationUrl = null,
        ?string         $returnUrl = null,
    ): string {
        $url = Url::registerTransaction($forceSandbox);

        if (!$credentials instanceof CredentialsData) {
            $credentials = Config::credentials();
        }

        $client = self::authenticatedClient($credentials);

        $bodyRequired = [
            'merchantId' => $credentials->merchantId,
            'posId' => $credentials->posId ?? $credentials->merchantId,
            'sessionId' => $payment->sessionId,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'email' => $payment->email,
            'country' => $payment->country,
            'language' => $payment->language,
            'urlReturn' => $returnUrl ?? Url::return($payment->sessionId),
            'sign' => self::encodeSignature(
                sessionId: $payment->sessionId,
                amount: $payment->amount,
                currency: $payment->currency,
                credentials: $credentials,
            ),
        ];

        $body = [
            'client' => $payment->client,
            'address' => $payment->address,
            'zip' => $payment->postal,
            'city' => $payment->city,
            'phone' => $payment->phone,
            'urlStatus' => $validationUrl ?? Config::validationUrl(),
            'timeLimit' => $payment->timeLimit ?? Config::timeLimit(),
            'waitForResult' => $payment->waitForTransactionEnd ?? Config::waitForTransactionEnd(),
            'encoding' => 'UTF-8',
        ];

        $response = $client->post($url, [
            RequestOptions::JSON => array_merge($bodyRequired, $body),
        ]);

        if ($response->getStatusCode() >= 400) {
            Przelewy24Exception::throw('przelewy24_invalid_response', $response->getStatusCode());
        }

        $responseContent = Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);
        $token = $responseContent['data']['token'] ?? '';

        return Url::paymentForward($token);
    }

    /**
     * @throws GuzzleException
     * @throws Przelewy24Exception
     */
    public static function verifyTransaction(VerificationData $verification, bool $forceSandbox = false, ?CredentialsData $credentials = null): array
    {
        $url = Url::verifyTransaction($forceSandbox);

        if (!$credentials instanceof CredentialsData) {
            $credentials = Config::credentials();
        }

        $client = self::authenticatedClient($credentials);

        $body = [
            'merchantId' => $credentials->merchantId,
            'posId' => $credentials->posId ?? $credentials->merchantId,
            'sessionId' => $verification->sessionId,
            'amount' => $verification->amount,
            'currency' => $verification->currency,
            'orderId' => $verification->orderId,
            'sign' => self::encodeSignature(
                sessionId: $verification->sessionId,
                amount: $verification->amount,
                currency: $verification->currency,
                orderId: $verification->orderId,
                credentials: $credentials,
            ),
        ];

        $response = $client->put($url, [
            RequestOptions::JSON => $body,
        ]);

        if ($response->getStatusCode() >= 400) {
            Przelewy24Exception::throw('przelewy24_invalid_response', $response->getStatusCode());
        }

        return Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @throws GuzzleException
     * @throws Przelewy24Exception
     */
    public static function transactionInfo(string $sessionId, bool $forceSandbox = false, CredentialsData $credentials = null): array
    {
        $url = Url::transactionInfo($sessionId, $forceSandbox);

        if (!$credentials instanceof CredentialsData) {
            $credentials = Config::credentials();
        }

        $client = self::authenticatedClient($credentials);

        $response = $client->get($url, ['http_errors' => false]);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            Przelewy24Exception::throw('przelewy24_transaction_not_found', Response::HTTP_NOT_FOUND);
        } elseif ($response->getStatusCode() >= 400) {
            Przelewy24Exception::throw('przelewy24_invalid_response', $response->getStatusCode());
        }

        return Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);
    }

    public static function encodeSignature(string $sessionId, int $amount, string $currency = 'PL', ?int $orderId = null, ?CredentialsData $credentials = null): string
    {
        if (!$credentials instanceof CredentialsData) {
            $credentials = Config::credentials();
        }

        $data = ['sessionId' => $sessionId];

        if (is_null($orderId)) {
            $data['merchantId'] = $credentials->merchantId;
        } else {
            $data['orderId'] = $orderId;
        }

        $data['amount'] = $amount;
        $data['currency'] = $currency;
        $data['crc'] = $credentials->crc;

        return Encoder::hashedJson(
            $data,
            'sha384',
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private static function authenticatedClient(CredentialsData $credentials): GuzzleClient
    {
        return self::guzzleClient([
            'auth' => [$credentials->merchantId, $credentials->secretId],
        ]);
    }
}
