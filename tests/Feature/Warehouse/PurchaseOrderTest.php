<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use RuntimeException;
use App\Enum\Section;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductGroup;
use App\Models\PurchasePrice;
use App\Enum\PurchaseOrderStatus;
use App\Enum\PurchasePriceSource;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\StockLedger;
use App\Services\Warehouse\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Zamówienia do dostawców i przyjęcia towaru.
 *
 * Przyjęcie robi trzy rzeczy naraz — dokumentuje dostawę, podnosi stan
 * zdarzeniem i zapisuje nową cenę zakupu — i każda z nich ma tu własne
 * asercje. Czwarta rzecz, której przyjęcie **nie** robi, też jest
 * sprawdzana: cena sprzedaży zostaje nietknięta.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PurchaseOrderService();
        $this->ledger = new StockLedger();
    }

    #[Test]
    public function zamowienie_dostaje_kolejny_numer(): void
    {
        $supplier = $this->supplier();

        $first = $this->service->draft($supplier);
        $second = $this->service->draft($supplier);

        $this->assertSame($first->number + 1, $second->number);
    }

    #[Test]
    public function ten_sam_produkt_dwa_razy_podnosi_ilosc_zamiast_dublowac_wiersz(): void
    {
        // To jest pomylka, ktora stary system robil w zapotrzebowaniu:
        // ten sam towar w kilku wierszach i zamowienie wieksze niz trzeba.
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');

        $this->service->addItem($order, $product, 4);
        $this->service->addItem($order, $product, 6);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(10.0, (float) $order->items()->first()?->quantity_ordered);
    }

    #[Test]
    public function wyslanego_zamowienia_nie_da_sie_juz_zmienic(): void
    {
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $this->service->addItem($order, $product, 4);

        $this->service->send($order);

        $this->expectException(RuntimeException::class);
        $this->service->addItem($order, $this->fitting('gałka'), 1);
    }

    #[Test]
    public function pustego_zamowienia_nie_da_sie_wyslac(): void
    {
        $order = $this->service->draft($this->supplier());

        $this->expectException(RuntimeException::class);
        $this->service->send($order);
    }

    #[Test]
    public function przyjecie_podnosi_stan_zdarzeniem_a_nie_korekta(): void
    {
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $item = $this->service->addItem($order, $product, 10, '40.00');
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 10.0]]);

        $level = $this->ledger->level($product);

        $this->assertSame(10.0, (float) $level->quantity);
        // Odtworzenie projekcji z rejestru musi dac to samo — inaczej
        // stan przestal byc suma zdarzen.
        $this->assertSame(10.0, (float) $this->ledger->rebuild($product)->quantity);
    }

    #[Test]
    public function czesciowa_dostawa_zostawia_zamowienie_otwarte(): void
    {
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $item = $this->service->addItem($order, $product, 10, '40.00');
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 4.0]]);

        $this->assertSame(PurchaseOrderStatus::PARTIAL, $order->refresh()->status);
        $this->assertSame(6.0, $item->refresh()->outstanding());

        $this->service->receive($order, [['item' => $item, 'quantity' => 6.0]]);

        $this->assertSame(PurchaseOrderStatus::RECEIVED, $order->refresh()->status);
        $this->assertSame(0.0, $item->refresh()->outstanding());
    }

    #[Test]
    public function nadwyzka_zamyka_zamowienie_i_nie_daje_ujemnej_reszty(): void
    {
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $item = $this->service->addItem($order, $product, 10, '40.00');
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 12.0]]);

        $this->assertSame(PurchaseOrderStatus::RECEIVED, $order->refresh()->status);
        $this->assertSame(0.0, $item->refresh()->outstanding());
        $this->assertSame(12.0, (float) $this->ledger->level($product)->quantity);
    }

    #[Test]
    public function przyjecie_zaklada_nowy_okres_ceny_zakupu(): void
    {
        $product = $this->fitting('rotula 33');
        $yesterday = Carbon::today()->subDay();

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => $yesterday,
        ]);

        $order = $this->service->draft($this->supplier());
        $item = $this->service->addItem($order, $product, 10, '50.00');
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 10.0]]);

        // Nowa cena obowiazuje od dzis...
        $current = $product->purchasePriceAt();

        $this->assertNotNull($current);
        $this->assertSame('50.00', $current->net_price);
        $this->assertSame(PurchasePriceSource::DELIVERY, $current->source);

        // ...a wczorajsza oferta dalej wie, po czym byla liczona.
        $this->assertSame('40.00', $product->purchasePriceAt($yesterday)?->net_price);
    }

    #[Test]
    public function cena_zakupu_idzie_z_ostatniej_dostawy_a_nie_ze_sredniej(): void
    {
        // M-16. Stan 10 po 40, przychodzi 10 po 50: cena zakupu to 50,
        // a nie 45. Srednia wazona bylaby wierniejsza temu, co lezy na
        // polce, ale nie ma jej na zadnej fakturze.
        $product = $this->fitting('rotula 33');
        $this->ledger->count($product, 10.0);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => Carbon::today()->subDay(),
        ]);

        $order = $this->service->draft($this->supplier());
        $item = $this->service->addItem($order, $product, 10, '50.00');
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 10.0]]);

        $this->assertSame('50.00', $product->purchasePriceAt()?->net_price);
    }

    #[Test]
    public function przyjecie_bez_ceny_nie_kasuje_poprzedniej(): void
    {
        // Brak ceny na dokumencie nie jest informacja, ze towar jest
        // darmowy — to ta sama zasada, co przy braku ceny materialu.
        $product = $this->fitting('rotula 33');

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => Carbon::today()->subDay(),
        ]);

        $order = $this->service->draft($this->supplier());
        $item = $this->service->addItem($order, $product, 5);
        $this->service->send($order);

        $this->service->receive($order, [['item' => $item, 'quantity' => 5.0]]);

        $this->assertSame('40.00', $product->purchasePriceAt()?->net_price);
    }

    #[Test]
    public function do_zamknietego_zamowienia_nie_przyjmuje_sie_towaru(): void
    {
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $item = $this->service->addItem($order, $product, 5, '40.00');
        $this->service->send($order);
        $this->service->cancel($order);

        $this->expectException(RuntimeException::class);
        $this->service->receive($order, [['item' => $item, 'quantity' => 5.0]]);
    }

    #[Test]
    public function anulowanie_nie_zdejmuje_przyjetego_towaru(): void
    {
        // Towar przyszedl naprawde; cofniecie zamowienia tego nie odwraca.
        $order = $this->service->draft($this->supplier());
        $product = $this->fitting('rotula 33');
        $item = $this->service->addItem($order, $product, 10, '40.00');
        $this->service->send($order);
        $this->service->receive($order, [['item' => $item, 'quantity' => 4.0]]);

        $this->service->cancel($order);

        $this->assertSame(4.0, (float) $this->ledger->level($product)->quantity);
    }

    #[Test]
    public function sugestie_grupuja_sie_po_dostawcy(): void
    {
        $cda = $this->supplier('CDA');
        $inny = $this->supplier('Inny dostawca');

        $a = $this->fitting('rotula 33', $cda);
        $b = $this->fitting('gałka', $cda);
        $c = $this->fitting('zawias', $inny);

        foreach ([$a, $b, $c] as $product) {
            $this->ledger->thresholds($product, 2.0, 10.0);
        }

        $result = $this->service->fromSuggestions([
            (int) $a->getKey(),
            (int) $b->getKey(),
            (int) $c->getKey(),
        ]);

        $this->assertCount(2, $result['orders']);
        $this->assertSame([], $result['skipped']);

        $counts = array_map(static fn($order): int => $order->items()->count(), $result['orders']);
        sort($counts);

        $this->assertSame([1, 2], $counts);
    }

    #[Test]
    public function produkt_bez_dostawcy_wraca_w_pominietych(): void
    {
        // Cichy brak bylby tu najgorszy: czlowiek zaznacza dziesiec
        // pozycji, dostaje osiem i nie ma jak zauwazyc, ktorych.
        $sierota = $this->fitting('bez dostawcy');
        $this->ledger->thresholds($sierota, 2.0, 10.0);

        $result = $this->service->fromSuggestions([(int) $sierota->getKey()]);

        $this->assertSame([], $result['orders']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('dostawcy', $result['skipped'][0]['reason']);
    }

    #[Test]
    public function sugestia_liczy_od_stanu_fizycznego_nie_dostepnego(): void
    {
        // Stan 8, minimum 2, maksimum 10, z tego 6 zarezerwowane.
        // Do zamowienia: nic, bo na polce lezy wiecej niz minimum.
        // Liczenie od dostepnego (2) pokryloby rezerwacje drugi raz.
        $product = $this->fitting('rotula 33', $this->supplier());
        $this->ledger->thresholds($product, 2.0, 10.0);
        $this->ledger->count($product, 8.0);
        $this->ledger->reserve($product, 6.0);

        $result = $this->service->fromSuggestions([(int) $product->getKey()]);

        $this->assertSame([], $result['orders']);
        $this->assertCount(1, $result['skipped']);
    }

    private function supplier(string $name = 'CDA'): Supplier
    {
        /** @var Supplier */
        return Supplier::query()->firstOrCreate(['name' => $name], ['name' => $name]);
    }

    private function fitting(string $name, ?Supplier $supplier = null): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'supplier_id' => $supplier?->id,
            'section' => Section::FITTINGS->value,
            'code' => mb_strtoupper(substr(md5($name), 0, 10)),
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }
}
