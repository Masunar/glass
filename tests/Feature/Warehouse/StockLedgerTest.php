<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use RuntimeException;
use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Product;
use App\Models\Location;
use App\Models\ProductGroup;
use App\Models\StockMovement;
use App\Enum\StockMovementType;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Rejestr ruchów magazynowych.
 *
 * Testy pilnują jednej zasady i jej trzech konsekwencji: **stan jest
 * sumą udokumentowanych zdarzeń**, więc nie da się go nadpisać, da się
 * go odtworzyć z rejestru, a każda zmiana zostawia ślad — łącznie
 * z inwentaryzacją.
 */
class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new StockLedger();
    }

    private function fitting(string $name = 'rotula 33 imbus'): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }

    #[Test]
    public function przyjecie_i_wydanie_skladaja_sie_na_stan(): void
    {
        $product = $this->fitting();

        $this->ledger->receive($product, 12);
        $level = $this->ledger->issue($product, 5);

        $this->assertSame('7.000', (string) $level->quantity);
        $this->assertSame(2, StockMovement::query()->count());
    }

    #[Test]
    public function nie_wydamy_wiecej_niz_lezy_na_polce(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 2);

        // Ujemny stan fizyczny nie opisuje niczego, co da sie wziac
        // z regalu. Gdyby stan naprawde byl inny, prostuje to spis.
        $this->expectException(RuntimeException::class);
        $this->ledger->issue($product, 3);
    }

    #[Test]
    public function zarezerwowac_mozna_wiecej_niz_jest_i_to_widac(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 3);

        // Obietnica zlozona klientowi jest faktem niezaleznie od stanu.
        // Niedobor to sygnal dla zakupow, nie blokada sprzedazy.
        $level = $this->ledger->reserve($product, 5);

        $this->assertSame('3.000', (string) $level->quantity);
        $this->assertSame('5.000', (string) $level->reserved);
        $this->assertSame(-2.0, $level->available());
    }

    #[Test]
    public function rezerwacja_nie_rusza_stanu_fizycznego(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 10);
        $this->ledger->reserve($product, 4);
        $level = $this->ledger->release($product, 4);

        $this->assertSame('10.000', (string) $level->quantity);
        $this->assertSame('0.000', (string) $level->reserved);
    }

    #[Test]
    public function inwentaryzacja_zapisuje_roznice_a_nie_wynik(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 5);

        $level = $this->ledger->count($product, 3, note: 'spis roczny');

        /** @var StockMovement $correction */
        $correction = StockMovement::query()
            ->where('type', StockMovementType::CORRECTION->value)
            ->firstOrFail();

        // W rejestrze ma zostac „bylo 5, jest 3", a nie sama trojka
        // bez pochodzenia.
        $this->assertSame('-2.000', (string) $correction->quantity);
        $this->assertSame('spis roczny', $correction->note);
        $this->assertSame('3.000', (string) $level->quantity);
    }

    #[Test]
    public function spis_zgodny_ze_stanem_nie_zostawia_wiersza(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 5);

        $this->ledger->count($product, 5);

        $this->assertSame(0, StockMovement::query()
            ->where('type', StockMovementType::CORRECTION->value)
            ->count());
    }

    #[Test]
    public function projekcja_odtwarza_sie_z_rejestru(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 12);
        $this->ledger->issue($product, 5);
        $this->ledger->reserve($product, 4);
        $this->ledger->count($product, 6);

        $level = $this->ledger->level($product);

        // Rozjazd wstawiony recznie — tak, jak zrobilby go blad w kodzie
        // albo zapytanie SQL z palca.
        $level->quantity = '999.000';
        $level->reserved = '999.000';
        $level->save();

        $rebuilt = $this->ledger->rebuild($product);

        $this->assertSame('6.000', (string) $rebuilt->quantity);
        $this->assertSame('4.000', (string) $rebuilt->reserved);
    }

    #[Test]
    public function sugestia_zakupowa_liczy_sie_od_progu(): void
    {
        $product = $this->fitting();
        $this->ledger->thresholds($product, min: 2, max: 12);

        // Stan 0 < Min 2, wiec do zamowienia 12 - 0. Wzor potwierdzony
        // na siedmiu pozycjach starego systemu (40-magazyn.md par. 3.2).
        $this->assertSame(12.0, $this->ledger->level($product)->toOrder());

        $this->ledger->receive($product, 6);

        // Stan 6 >= Min 2, wiec nic nie zamawiamy, mimo ze do Max daleko.
        $this->assertSame(0.0, $this->ledger->level($product)->toOrder());
    }

    #[Test]
    public function rezerwacja_nie_podnosi_sugestii_zakupowej(): void
    {
        $product = $this->fitting();
        $this->ledger->thresholds($product, min: 2, max: 12);
        $this->ledger->receive($product, 6);
        $this->ledger->reserve($product, 6);

        // Rezerwacja mowi, komu towar obiecano, a nie ze go nie ma
        // na polce. Liczenie progu od dostepnego kazaloby zamawiac
        // towar, ktory wlasnie lezy w magazynie.
        $this->assertSame(0.0, $this->ledger->level($product)->toOrder());
    }

    #[Test]
    public function ruch_ujemny_poza_korekta_jest_odrzucany(): void
    {
        $product = $this->fitting();

        $this->expectException(RuntimeException::class);
        $this->ledger->receive($product, -3);
    }

    #[Test]
    public function stan_trzyma_sie_lokalizacji(): void
    {
        $product = $this->fitting();

        /** @var Location $other */
        $other = Location::query()->where('name', 'Chopina')->firstOrFail();

        $this->ledger->receive($product, 10);
        $this->ledger->receive($product, 4, $other);

        // M-11 jest otwarte, ale model ma je unieść: stan w Stobnie nie
        // jest stanem w Chopinie i nie wolno ich zsumować po cichu.
        $this->assertSame('10.000', (string) $this->ledger->level($product)->quantity);
        $this->assertSame('4.000', (string) $this->ledger->level($product, $other)->quantity);
    }
}
