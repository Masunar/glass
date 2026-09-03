<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Services\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Numeracja zleceń.
 *
 * Numer jest tym, czym klient posługuje się przez telefon, więc dwa
 * zlecenia o tym samym numerze to realny problem. Ciąg ze starego
 * systemu ma być kontynuowany, a nie zaczynany od nowa.
 */
class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    private NumberSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sequence = new NumberSequence();
    }

    public function test_pierwszy_numer_bierze_sie_z_progu(): void
    {
        $this->assertSame(23908, $this->sequence->next(NumberSequence::ORDERS, 23908));
    }

    public function test_kolejne_numery_rosna_o_jeden(): void
    {
        $first = $this->sequence->next(NumberSequence::ORDERS, 23908);

        $this->assertSame($first + 1, $this->sequence->next(NumberSequence::ORDERS, 23908));
        $this->assertSame($first + 2, $this->sequence->next(NumberSequence::ORDERS, 23908));
    }

    public function test_prog_nie_cofa_juz_wydanych_numerow(): void
    {
        $this->sequence->next(NumberSequence::ORDERS, 23908);
        $this->sequence->next(NumberSequence::ORDERS, 23908);

        // Nizszy prog nie moze cofnac ciagu - numer raz wydany jest zajety.
        $this->assertSame(23910, $this->sequence->next(NumberSequence::ORDERS, 1));
    }

    public function test_ciag_ustawiany_po_migracji_danych(): void
    {
        // Po wgraniu zlecen ze starego systemu numeracja ma isc dalej
        // od pierwszego wolnego, a nie nadpisywac istniejacych.
        $this->sequence->seedFrom(NumberSequence::ORDERS, 24046);

        $this->assertSame(24047, $this->sequence->next(NumberSequence::ORDERS));
    }

    public function test_dziedziny_sa_niezalezne(): void
    {
        $this->sequence->next(NumberSequence::ORDERS, 23908);
        $this->sequence->next(NumberSequence::ORDERS, 23908);

        $this->assertSame(1, $this->sequence->next('offers'));
        $this->assertSame(23910, $this->sequence->next(NumberSequence::ORDERS));
    }

    public function test_numery_nie_powtarzaja_sie_w_serii(): void
    {
        $numbers = [];

        for ($i = 0; $i < 50; $i++) {
            $numbers[] = $this->sequence->next(NumberSequence::ORDERS, 23908);
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame(range(23908, 23957), $numbers);
    }

    public function test_ciag_zapisuje_sie_w_bazie(): void
    {
        $this->sequence->next(NumberSequence::ORDERS, 23908);

        $this->assertSame(
            23909,
            (int) DB::table('number_sequences')
                ->where('domain', NumberSequence::ORDERS)
                ->value('next_value'),
        );
    }
}
