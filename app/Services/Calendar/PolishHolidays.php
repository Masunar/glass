<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

/**
 * Święta ustawowe w Polsce (ustawa o dniach wolnych od pracy).
 *
 * Liczone, nie wpisywane: daty stałe są w ustawie, a ruchome wynikają
 * z Wielkanocy. Słownik do uzupełniania co roku byłby pierwszą rzeczą,
 * o której ktoś zapomni — a termin wypadający w Boże Ciało wychodziłby
 * wtedy o dzień za wcześnie bez żadnego sygnału.
 *
 * Święta niedzielne (Wielkanoc, Zielone Świątki) są na liście dla
 * kompletności; w liczeniu dni roboczych i tak odpada niedziela.
 */
final class PolishHolidays
{
    /** Stałe daty „miesiąc-dzień". */
    private const FIXED = [
        '01-01' => 'Nowy Rok',
        '01-06' => 'Trzech Króli',
        '05-01' => 'Święto Pracy',
        '05-03' => 'Święto Konstytucji 3 Maja',
        '08-15' => 'Wniebowzięcie NMP',
        '11-01' => 'Wszystkich Świętych',
        '11-11' => 'Święto Niepodległości',
        '12-25' => 'Boże Narodzenie',
        '12-26' => 'Drugi dzień Bożego Narodzenia',
    ];

    /** Wigilia jest dniem wolnym od 2025 r. */
    private const CHRISTMAS_EVE_SINCE = 2025;

    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    /**
     * Święta danego roku: data RRRR-MM-DD => nazwa.
     *
     * @return array<string, string>
     */
    public static function forYear(int $year): array
    {
        if (isset(self::$cache[$year])) {
            return self::$cache[$year];
        }

        $days = [];

        foreach (self::FIXED as $monthDay => $name) {
            $days[sprintf('%04d-%s', $year, $monthDay)] = $name;
        }

        if ($year >= self::CHRISTMAS_EVE_SINCE) {
            $days[sprintf('%04d-12-24', $year)] = 'Wigilia Bożego Narodzenia';
        }

        $easter = self::easter($year);

        $days[$easter->toDateString()] = 'Wielkanoc';
        $days[$easter->addDay()->toDateString()] = 'Poniedziałek Wielkanocny';
        $days[$easter->addDays(49)->toDateString()] = 'Zielone Świątki';
        $days[$easter->addDays(60)->toDateString()] = 'Boże Ciało';

        ksort($days);

        return self::$cache[$year] = $days;
    }

    public static function isHoliday(CarbonImmutable $day): bool
    {
        return isset(self::forYear($day->year)[$day->toDateString()]);
    }

    /**
     * Niedziela Wielkanocna w kalendarzu gregoriańskim (algorytm
     * Meeusa/Jonesa/Butchera). Bez rozszerzenia `calendar` PHP, którego
     * obraz produkcyjny nie musi mieć.
     */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
