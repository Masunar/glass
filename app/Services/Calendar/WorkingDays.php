<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\DayOff;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

/**
 * Dni robocze zakładu: bez sobót, niedziel, świąt ustawowych
 * (`PolishHolidays`) i dni wolnych ze słownika (`DayOff`).
 *
 * Jedyne miejsce, które wie, czy dany dzień jest roboczy — termin
 * zlecenia i każda przyszła data „za N dni roboczych" mają liczyć
 * tak samo.
 */
final readonly class WorkingDays
{
    /**
     * Data po `$days` dniach roboczych, licząc od dnia po `$from`.
     *
     * Zero dni to `$from`, a gdy ten dzień jest wolny — najbliższy
     * roboczy: zlecenie bez pracy nie wyjdzie w niedzielę.
     */
    public function add(CarbonInterface $from, int $days): CarbonImmutable
    {
        $day = CarbonImmutable::instance($from)->startOfDay();
        $closed = $this->closed($day);

        if ($days <= 0) {
            while (!$this->isWorking($day, $closed)) {
                $day = $day->addDay();
            }

            return $day;
        }

        $left = $days;

        while ($left > 0) {
            $day = $day->addDay();

            if ($this->isWorking($day, $closed)) {
                $left--;
            }
        }

        return $day;
    }

    /**
     * @param array<string, true> $closed
     */
    private function isWorking(CarbonImmutable $day, array $closed): bool
    {
        return !$day->isWeekend()
            && !PolishHolidays::isHoliday($day)
            && !isset($closed[$day->toDateString()]);
    }

    /**
     * Dni wolne zakładu od podanej daty. Słownik ma kilka pozycji
     * w roku, więc jedno zapytanie bez górnej granicy.
     *
     * @return array<string, true>
     */
    private function closed(CarbonImmutable $from): array
    {
        $closed = [];

        /** @var iterable<DayOff> $days */
        $days = DayOff::query()
            ->where('is_active', true)
            ->where('date', '>=', $from->toDateString())
            ->get(['date']);

        foreach ($days as $day) {
            $closed[$day->date->toDateString()] = true;
        }

        return $closed;
    }
}
