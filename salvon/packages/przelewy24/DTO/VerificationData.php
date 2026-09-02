<?php

declare(strict_types=1);

namespace Salvon\Przelewy24\DTO;

use JsonException;

final readonly class VerificationData
{
    public function __construct(
        public int    $orderId,
        public string $sessionId,
        public int    $amount,
        public string $currency = 'PLN',
    ) {}

    /**
     * @throws JsonException
     */
    public static function from(string $getContent): VerificationData
    {
        $data = json_decode($getContent, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            orderId: $data['orderId'] ?? null,
            sessionId: $data['sessionId'] ?? null,
            amount: $data['amount'] ?? null,
            currency: $data['currency'] ?? null,
        );
    }
}
