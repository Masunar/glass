<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Stan zamówienia do dostawcy.
 *
 * Stary system miał na to trzy zakładki — „Lista zamówień", „Lista
 * zamówień otwartych", „Lista zarchiwizowanych" — czyli trzy ekrany
 * różniące się wyłącznie filtrem. Tutaj jest jedna lista i ten enum.
 *
 * `PARTIAL` nie jest stanem, który ktoś ustawia: wynika z porównania
 * ilości zamówionych z przyjętymi i ustawia go samo przyjęcie.
 */
enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIAL = 'partial';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    /** Czy zamówienie nadal czeka na towar. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::DRAFT, self::SENT, self::PARTIAL => true,
            self::RECEIVED, self::CANCELLED => false,
        };
    }

    /** Czy na tym etapie wolno jeszcze zmieniać pozycje. */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Szkic',
            self::SENT => 'Wysłane',
            self::PARTIAL => 'Częściowo przyjęte',
            self::RECEIVED => 'Zrealizowane',
            self::CANCELLED => 'Anulowane',
        };
    }
}
