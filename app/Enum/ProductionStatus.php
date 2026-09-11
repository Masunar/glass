<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Stan etapu produkcyjnego.
 *
 * Cztery stany, nie dwa. „Zrobione / niezrobione" wystarczyłoby do
 * odhaczania, ale nie odpowiada na pytanie, po co ten moduł powstał:
 * gdzie zlecenie stoi. `IN_PROGRESS` mówi, że ktoś przy tym siedzi,
 * `PROBLEM` — że stoi i nie ruszy samo.
 */
enum ProductionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case PROBLEM = 'problem';

    /** Etap zamknięty — nie blokuje już przejścia zlecenia dalej. */
    public function isClosed(): bool
    {
        return $this === self::DONE;
    }
}
