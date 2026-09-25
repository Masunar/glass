<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Po co przychodzi dostawa dodatkowa do zlecenia.
 *
 * Lista z uwag klienta (25.09): reklamacja, błędne okucie, domówienie.
 * „Inne" na przypadek, którego lista nie przewidziała — powód i tak
 * idzie w uwadze.
 */
enum ExtraDeliveryReason: string
{
    case CLAIM = 'claim';
    case WRONG_ITEM = 'wrong_item';
    case REORDER = 'reorder';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLAIM => 'Reklamacja',
            self::WRONG_ITEM => 'Błędne okucie',
            self::REORDER => 'Domówienie',
            self::OTHER => 'Inne',
        };
    }
}
