<?php

declare(strict_types=1);

namespace App\Dictionaries;

/**
 * Typ pola słownika — decyduje o kontrolce w formularzu i o regule
 * walidacyjnej. Celowo wąska lista: słownik, który potrzebuje pola
 * spoza niej, nie jest już słownikiem i zasługuje na własny ekran.
 */
enum FieldType: string
{
    case TEXT = 'text';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    /** Wybór ze stałej listy wartości podanej w definicji pola. */
    case SELECT = 'select';
    /** Wybór wiersza innego słownika — lista budowana w locie. */
    case REFERENCE = 'reference';
}
