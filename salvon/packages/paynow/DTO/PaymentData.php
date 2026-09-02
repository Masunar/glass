<?php

declare(strict_types=1);

namespace Salvon\Paynow\DTO;

final readonly class PaymentData
{
    public function __construct(
        public int     $amount,
        public string  $externalId,
        public string  $description,
        public string  $buyerEmail,
        public string  $buyerFirstName = '',
        public string  $buyerLastName = '',
        public string  $continueUrl = '',
        public string  $currency = 'PLN',
        public ?string $paymentMethodId = null,
    ) {}

    public function toArray(): array
    {
        $buyer = ['email' => $this->buyerEmail];

        if ($this->buyerFirstName !== '') {
            $buyer['firstName'] = $this->buyerFirstName;
        }

        if ($this->buyerLastName !== '') {
            $buyer['lastName'] = $this->buyerLastName;
        }

        $data = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'externalId' => $this->externalId,
            'description' => $this->description,
            'buyer' => $buyer,
        ];

        if ($this->continueUrl !== '') {
            $data['continueUrl'] = $this->continueUrl;
        }

        if ($this->paymentMethodId !== null) {
            $data['paymentMethodId'] = $this->paymentMethodId;
        }

        return $data;
    }
}
