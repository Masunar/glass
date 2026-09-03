<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizacja wartości z formularza.
 *
 * Pusty tekst to nie to samo co brak wartości — w bazie ma być NULL,
 * nie pusty string. Stara baza jest pełna pól wypełnionych spacją albo
 * zerem, bo formularz nie przepuszczał pustych, a rozróżnienie „nie
 * podano" od „podano nic" przepadło razem z nimi.
 */
final readonly class Normalize
{
    /**
     * Tekst bez otaczających spacji; pusty daje NULL.
     *
     * Przycinanie nie jest kosmetyką: kod produktu wpisany jako „ ABC "
     * nie znajdzie się później przy wyszukiwaniu po „ABC".
     */
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Same cyfry. NIP i telefon wpisuje się raz ze spacjami, raz bez,
     * a numer podany przez telefon nigdy nie ma formatu z bazy.
     */
    public static function digits(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';

        return $digits === '' ? null : $digits;
    }
}
