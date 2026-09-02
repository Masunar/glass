<?php

declare(strict_types=1);

namespace Salvon\Przelewy24\DTO;

final readonly class PaymentData
{
    public function __construct(
        public string $sessionId,
        public int    $amount,
        public string $description,
        public string $email,
        public string $country = 'PL',
        public string $language = 'pl',
        public string $currency = 'PLN',
        public string $client = '',
        public string $address = '',
        public string $postal = '',
        public string $city = '',
        public string $phone = '',
        public ?int   $timeLimit = null,
        public bool   $waitForTransactionEnd = true,
    ) {}
}
