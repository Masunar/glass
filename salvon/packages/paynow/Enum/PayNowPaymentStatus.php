<?php

declare(strict_types=1);

namespace Salvon\Paynow\Enum;

enum PayNowPaymentStatus: string
{
    public function isFinal(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::REJECTED, self::ERROR, self::EXPIRED, self::ABANDONED => true,
            default => false,
        };
    }

    public function isPaid(): bool
    {
        return $this === self::CONFIRMED;
    }
    case NEW = 'NEW';
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case REJECTED = 'REJECTED';
    case ERROR = 'ERROR';
    case EXPIRED = 'EXPIRED';
    case ABANDONED = 'ABANDONED';
}
