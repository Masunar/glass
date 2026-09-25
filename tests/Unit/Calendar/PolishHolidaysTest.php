<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Calendar\PolishHolidays;

/**
 * Święta ustawowe: stałe z ustawy, ruchome od Wielkanocy.
 */
class PolishHolidaysTest extends TestCase
{
    #[Test]
    public function wielkanoc_zgadza_sie_z_kalendarzem(): void
    {
        $this->assertSame('2024-03-31', PolishHolidays::easter(2024)->toDateString());
        $this->assertSame('2025-04-20', PolishHolidays::easter(2025)->toDateString());
        $this->assertSame('2026-04-05', PolishHolidays::easter(2026)->toDateString());
        $this->assertSame('2027-03-28', PolishHolidays::easter(2027)->toDateString());
    }

    #[Test]
    public function ruchome_swieta_licza_sie_od_wielkanocy(): void
    {
        $days = PolishHolidays::forYear(2026);

        $this->assertSame('Poniedziałek Wielkanocny', $days['2026-04-06']);
        $this->assertSame('Zielone Świątki', $days['2026-05-24']);
        $this->assertSame('Boże Ciało', $days['2026-06-04']);
    }

    #[Test]
    public function wigilia_jest_wolna_od_2025(): void
    {
        $this->assertFalse(PolishHolidays::isHoliday(CarbonImmutable::parse('2024-12-24')));
        $this->assertTrue(PolishHolidays::isHoliday(CarbonImmutable::parse('2025-12-24')));
    }

    #[Test]
    public function rok_ma_komplet_swiat_ustawowych(): void
    {
        // 9 stałych + Wigilia + Wielkanoc, Poniedziałek, Zielone Świątki, Boże Ciało.
        $this->assertCount(14, PolishHolidays::forYear(2026));
        $this->assertCount(13, PolishHolidays::forYear(2024));
    }
}
