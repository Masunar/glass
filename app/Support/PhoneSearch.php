<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Szukanie po numerze telefonu zapisanym tak, jak ktoś go wpisał.
 *
 * `Normalize::digits()` sprowadza **zapytanie** do samych cyfr, ale
 * w bazie numer leży w formie wpisanej przez człowieka:
 * `603 666 014`, `+48 123 456 789`, `91 45 42 475`. Porównanie
 * `phone LIKE '%603666%'` nie trafia w żaden z nich, więc szukanie po
 * telefonie działało wyłącznie dla numerów wpisanych ciągiem.
 *
 * Numeru **nie normalizujemy przy zapisie**: człowiek wpisuje go
 * w formacie, który sam czyta, i ma go tak zobaczyć na karcie.
 * Normalizujemy przy porównaniu — po obu stronach.
 */
final readonly class PhoneSearch
{
    /** Znaki, które ludzie wstawiają w numer i które nic nie znaczą. */
    private const SEPARATORS = [' ', '-', '(', ')', '+', '.', "\u{00A0}"];

    /**
     * Warunek „ten numer zawiera te cyfry", odporny na separatory.
     *
     * @param Builder<covariant \Illuminate\Database\Eloquent\Model> $query
     */
    public static function apply(Builder $query, string $column, string $digits, bool $or = true): void
    {
        $expression = self::expression($column);
        $binding = '%' . $digits . '%';

        if ($or) {
            $query->orWhereRaw($expression . ' LIKE ?', [$binding]);

            return;
        }

        $query->whereRaw($expression . ' LIKE ?', [$binding]);
    }

    /**
     * Kolumna z wyciętymi separatorami.
     *
     * Wyrażenie nie skorzysta z indeksu, ale numery są krótkie i jest
     * ich tyle, co kontrahentów — a alternatywą jest druga kolumna
     * trzymana w zgodzie z pierwszą przy każdym zapisie.
     */
    private static function expression(string $column): string
    {
        $sql = $column;

        foreach (self::SEPARATORS as $separator) {
            $sql = sprintf("REPLACE(%s, '%s', '')", $sql, $separator);
        }

        return $sql;
    }
}
