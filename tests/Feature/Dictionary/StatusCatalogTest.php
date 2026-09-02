<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Models\Status;
use App\Models\Location;
use App\Enum\StatusDomain;
use App\Models\StatusTransition;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Niezmienniki katalogu statusów.
 *
 * Stary system rozjechał katalog między modułami, bo statusy były zaszyte
 * w nazwach widoków SQL. Te testy pilnują, żeby katalog jako dane pozostał
 * spójny — a spójność da się tu sprawdzić, czego wcześniej nie dało się
 * zrobić w ogóle.
 */
class StatusCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new StatusSeeder())->run();
    }

    public function test_kazda_dziedzina_ma_dokladnie_jeden_status_domyslny(): void
    {
        foreach (StatusDomain::cases() as $domain) {
            $defaults = Status::query()
                ->where('domain', $domain->value)
                ->where('is_default', true)
                ->count();

            $this->assertSame(
                1,
                $defaults,
                sprintf('Dziedzina "%s" ma %d statusów domyślnych zamiast jednego.', $domain->value, $defaults),
            );
        }
    }

    public function test_zaden_status_koncowy_nie_ma_przejscia_wychodzacego(): void
    {
        $finalWithExit = StatusTransition::query()
            ->whereHas('fromStatus', static fn($query) => $query->where('is_final', true))
            ->with('fromStatus')
            ->get();

        $this->assertCount(
            0,
            $finalWithExit,
            'Statusy końcowe nie mogą prowadzić dalej: ' . $finalWithExit->pluck('fromStatus.code')->implode(', '),
        );
    }

    public function test_przejscie_laczy_statusy_z_tej_samej_dziedziny(): void
    {
        $transitions = StatusTransition::query()->with(['fromStatus', 'toStatus'])->get();

        $this->assertNotEmpty($transitions, 'Katalog przejść jest pusty.');

        foreach ($transitions as $transition) {
            $this->assertSame(
                $transition->domain->value,
                $transition->toStatus->domain->value,
                'Przejście prowadzi do statusu z innej dziedziny.',
            );

            if ($transition->fromStatus !== null) {
                $this->assertSame(
                    $transition->domain->value,
                    $transition->fromStatus->domain->value,
                    'Przejście wychodzi ze statusu z innej dziedziny.',
                );
            }
        }
    }

    public function test_zaden_status_zlecenia_nie_jest_slepa_uliczka(): void
    {
        $deadEnds = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_final', false)
            ->whereDoesntHave('transitionsFrom')
            ->pluck('code');

        $this->assertCount(
            0,
            $deadEnds,
            'Statusy bez żadnego wyjścia: ' . $deadEnds->implode(', '),
        );
    }

    public function test_oferta_odrzucona_jest_oddzielona_od_anulowania(): void
    {
        $rejected = Status::findByCode(StatusDomain::ORDER, 'OFERTA_ODRZUCONA');
        $cancelled = Status::findByCode(StatusDomain::ORDER, 'ANULOWANE');

        $this->assertNotNull($rejected, 'Brak statusu OFERTA_ODRZUCONA.');
        $this->assertNotNull($cancelled, 'Brak statusu ANULOWANE.');
        $this->assertTrue($rejected->is_final);
        $this->assertTrue($cancelled->is_final);
    }

    public function test_seeder_lokalizacji_daje_jeden_punkt_domyslny(): void
    {
        (new LocationSeeder())->run();

        $this->assertSame(2, Location::query()->count());
        $this->assertSame(1, Location::query()->where('is_default', true)->count());
    }
}
