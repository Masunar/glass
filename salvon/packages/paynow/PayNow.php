<?php

declare(strict_types=1);

namespace Salvon\Paynow;

use Paynow\Client;
use Paynow\Service\Payment;
use Paynow\Response\Payment\Status;
use Paynow\Exception\PaynowException;
use Paynow\Response\Payment\Authorize;
use Paynow\Model\PaymentMethods\PaymentMethod;
use Salvon\Paynow\DTO\PaymentData;

final class PayNow
{
    /**
     * @throws PaynowException
     */
    public static function createPayment(PaymentData $payment, ?string $idempotencyKey = null): Authorize
    {
        $idempotencyKey ??= uniqid($payment->externalId . '_', true);

        return self::payment()->authorize($payment->toArray(), $idempotencyKey);
    }

    /**
     * @throws PaynowException
     */
    public static function status(string $paymentId, ?string $idempotencyKey = null): Status
    {
        return self::payment()->status($paymentId, $idempotencyKey);
    }

    /**
     * @return array<int, PaymentMethod>
     * @throws PaynowException
     */
    public static function availableMethods(int $amount = 1000, string $currency = 'PLN', bool $applePay = true): array
    {
        return self::payment()
            ->getPaymentMethods($currency, $amount, $applePay)
            ->getAll();
    }

    private static function payment(): Payment
    {
        return new Payment(self::client());
    }

    private static function client(): Client
    {
        return new Client(
            Config::apiKey(),
            Config::signatureKey(),
            Config::environment(),
        );
    }
}
