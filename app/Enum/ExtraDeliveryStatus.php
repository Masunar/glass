<?php

declare(strict_types=1);

namespace App\Enum;

/** Stan dostawy dodatkowej. */
enum ExtraDeliveryStatus: string
{
    case EXPECTED = 'expected';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::EXPECTED => 'Oczekiwana',
            self::RECEIVED => 'Przyjęta',
            self::CANCELLED => 'Anulowana',
        };
    }
}
