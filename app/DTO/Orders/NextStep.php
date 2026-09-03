<?php

declare(strict_types=1);

namespace App\DTO\Orders;

use App\Models\Status;

/**
 * Jedno przejście statusu wraz z odpowiedzią, czy da się je teraz zrobić.
 *
 * Kolumna „co dalej" na liście pokazuje pierwsze dostępne przejście jako
 * przycisk. Zablokowane nie znika — staje się odnośnikiem do miejsca,
 * w którym brakuje danych, razem z powodem. Stary system pokazywał samą
 * listę statusów do wyboru i pozwalał wybrać każdy, więc zlecenie
 * potrafiło trafić do produkcji bez zaliczki.
 */
final readonly class NextStep
{
    public function __construct(
        public int $transitionId,
        public Status $target,
        public string $label,
        public bool $available,
        /** Powód blokady — pusty, gdy przejście jest dostępne. */
        public ?string $blockedBy = null,
        /**
         * Warunku nie da się dziś sprawdzić, bo moduł, który go rozstrzyga,
         * jeszcze nie istnieje. To nie to samo co warunek niespełniony:
         * pierwsze zniknie samo, drugie wymaga działania człowieka.
         */
        public bool $unknown = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transition_id' => $this->transitionId,
            'to_status' => $this->target->name,
            'to_status_code' => $this->target->code,
            'label' => $this->label,
            'available' => $this->available,
            'blocked_by' => $this->blockedBy,
            'unknown' => $this->unknown,
        ];
    }
}
