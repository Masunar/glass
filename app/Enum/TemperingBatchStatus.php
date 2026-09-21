<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Stan partii wysyłkowej do hartowni.
 *
 * Trzy zakładki starego systemu — „Do wysłania", „Wysłane",
 * „Zarchiwizowane" — to ten enum plus filtr, a nie trzy ekrany.
 */
enum TemperingBatchStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case RETURNED = 'returned';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function isOpen(): bool
    {
        return match ($this) {
            self::DRAFT, self::SENT, self::RETURNED => true,
            self::SETTLED, self::CANCELLED => false,
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Szkic',
            self::SENT => 'U podwykonawcy',
            self::RETURNED => 'Wróciła',
            self::SETTLED => 'Rozliczona',
            self::CANCELLED => 'Anulowana',
        };
    }
}
