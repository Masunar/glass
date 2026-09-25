<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use Tests\TestCase;
use App\Models\DayOff;
use Carbon\CarbonImmutable;
use App\Services\DictionaryService;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Calendar\WorkingDays;
use App\Dictionaries\DictionaryRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dni robocze: bez weekendów, świąt ustawowych i dni wolnych zakładu.
 */
class WorkingDaysTest extends TestCase
{
    use RefreshDatabase;

    private WorkingDays $days;

    protected function setUp(): void
    {
        parent::setUp();

        DayOff::query()->delete();
        $this->days = new WorkingDays();
    }

    private function add(string $from, int $days): string
    {
        return $this->days->add(CarbonImmutable::parse($from), $days)->toDateString();
    }

    #[Test]
    public function weekend_nie_jest_dniem_roboczym(): void
    {
        // Czwartek + 5: pt, pn, wt, sr, czw.
        $this->assertSame('2026-10-08', $this->add('2026-10-01', 5));
    }

    #[Test]
    public function swieta_ustawowe_sa_pomijane(): void
    {
        // 11 listopada (sroda).
        $this->assertSame('2026-11-12', $this->add('2026-11-10', 1));
        // Boze Cialo 4 czerwca 2026.
        $this->assertSame('2026-06-05', $this->add('2026-06-03', 1));
        // Wigilia, dwa dni swiat i weekend.
        $this->assertSame('2026-12-28', $this->add('2026-12-23', 1));
    }

    #[Test]
    public function dzien_wolny_zakladu_jest_pomijany_a_nieaktywny_nie(): void
    {
        DayOff::query()->create(['date' => '2026-10-05', 'name' => 'Inwentaryzacja', 'is_active' => true]);
        DayOff::query()->create(['date' => '2026-10-06', 'name' => 'Odwołany', 'is_active' => false]);

        $this->assertSame('2026-10-09', $this->add('2026-10-01', 5));
    }

    #[Test]
    public function zero_dni_w_sobote_to_poniedzialek(): void
    {
        $this->assertSame('2026-10-05', $this->add('2026-10-03', 0));
        $this->assertSame('2026-10-01', $this->add('2026-10-01', 0));
    }

    #[Test]
    public function dzien_wolny_zapisuje_sie_przez_slownik(): void
    {
        $service = new DictionaryService(new DictionaryRegistry());

        $result = $service->save('days-off', ['date' => '2026-10-05', 'name' => 'Inwentaryzacja', 'is_active' => true]);

        $this->assertSame([], $result['errors']);

        $rows = $service->rows('days-off');
        $this->assertSame('2026-10-05', $rows[0]['date']);
        $this->assertSame('Inwentaryzacja', $rows[0]['name']);

        // Ta sama nazwa rok pozniej to inny dzien, nie duplikat.
        $again = $service->save('days-off', ['date' => '2027-10-04', 'name' => 'Inwentaryzacja', 'is_active' => true]);
        $this->assertSame([], $again['errors']);

        $this->assertSame('2026-10-09', $this->add('2026-10-01', 5));
    }

    #[Test]
    public function zla_data_w_slowniku_jest_odrzucana(): void
    {
        $service = new DictionaryService(new DictionaryRegistry());

        $result = $service->save('days-off', ['date' => '5.10.2026', 'name' => 'Przestój', 'is_active' => true]);

        $this->assertArrayHasKey('date', $result['errors']);
    }
}
