<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\ProductGroup;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\OrderStock;
use App\Services\Warehouse\StockLedger;
use App\Services\Orders\OrderNextStep;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Okucia zlecenia wobec magazynu.
 *
 * Rezerwacja i rozchód są zdarzeniami powiązanymi ze statusem, nie
 * osobną czynnością do zapamiętania — to ona nie była wykonywana
 * w starym systemie i dlatego zlecenie 16492 miało status „Gotowe"
 * przy zerowym stanie wszystkich okuć.
 */
class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    private OrderStock $stock;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = new OrderStock();
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

    private function order(bool $included = true): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Magazyn ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);

        OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => $included ? 'component' : 'alternative',
            'is_included' => $included,
        ]);

        return $order->fresh() ?? $order;
    }

    private function addFitting(Order $order, Product $product, float $quantity): OrderItem
    {
        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        /** @var OrderItem */
        return OrderItem::query()->create([
            'order_list_id' => $list->id,
            'product_id' => $product->id,
            'section' => Section::FITTINGS->value,
            'name' => $product->name,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit_net_price' => '100.00',
            'amount' => number_format($quantity * 100, 2, '.', ''),
        ]);
    }

    #[Test]
    public function rezerwacja_nie_dubluje_sie_przy_powtornym_wejsciu(): void
    {
        $product = $this->fitting();
        $order = $this->order();
        $this->ledger->receive($product, 10);
        $this->addFitting($order, $product, 3);

        $this->stock->reserve($order);
        $this->stock->reserve($order);
        $this->stock->reserve($order);

        // Uzgadnianie, nie dokladanie: powrot na ten sam status zdarza
        // sie po kazdej poprawce wyceny.
        $this->assertSame('3.000', (string) $this->ledger->level($product)->reserved);
    }

    #[Test]
    public function zmniejszenie_pozycji_zwalnia_nadmiar(): void
    {
        $product = $this->fitting();
        $order = $this->order();
        $this->ledger->receive($product, 10);
        $item = $this->addFitting($order, $product, 5);

        $this->stock->reserve($order);

        $item->quantity = '2.00';
        $item->save();

        $this->stock->reserve($order);

        $this->assertSame('2.000', (string) $this->ledger->level($product)->reserved);
    }

    #[Test]
    public function wariant_ofertowy_nie_blokuje_towaru(): void
    {
        $product = $this->fitting();
        $order = $this->order(included: false);
        $this->ledger->receive($product, 10);
        $this->addFitting($order, $product, 4);

        $this->stock->reserve($order);

        // Wariant, ktorego klient nie wybral, nie ma prawa trzymac
        // okuc — to ta sama zasada, co przy kwocie i terminie.
        $this->assertSame('0.000', (string) $this->ledger->level($product)->reserved);
    }

    #[Test]
    public function wydanie_zamienia_rezerwacje_na_rozchod(): void
    {
        $product = $this->fitting();
        $order = $this->order();
        $this->ledger->receive($product, 10);
        $this->addFitting($order, $product, 4);

        $this->stock->reserve($order);
        $this->stock->issue($order);

        $level = $this->ledger->level($product);

        $this->assertSame('6.000', (string) $level->quantity);
        $this->assertSame('0.000', (string) $level->reserved);
    }

    #[Test]
    public function anulowanie_oddaje_towar(): void
    {
        $product = $this->fitting();
        $order = $this->order();
        $this->ledger->receive($product, 10);
        $this->addFitting($order, $product, 4);

        $this->stock->reserve($order);
        $this->stock->release($order);

        $this->assertSame('0.000', (string) $this->ledger->level($product)->reserved);
        $this->assertSame('10.000', (string) $this->ledger->level($product)->quantity);
    }

    #[Test]
    public function brak_okuc_blokuje_wejscie_na_produkcje_z_nazwa(): void
    {
        $product = $this->fitting('uchwyt LUMINIS');
        $order = $this->order();
        $this->ledger->receive($product, 1);
        $this->addFitting($order, $product, 3);

        // Warunek niespelniony wygrywa z nierozstrzygalnym, ale nie
        // z innym niespelnionym — a rysunki stoja w katalogu wczesniej.
        // Bez tego test sprawdzalby komunikat o rysunkach.
        $order->drawings_complete_at = now();
        $order->save();

        $steps = (new OrderNextStep())->forOrder($order->fresh(['lists.items']) ?? $order);
        $blocked = null;

        foreach ($steps as $step) {
            if (str_contains((string) $step->blockedBy, 'Brakuje okuć')) {
                $blocked = $step;
            }
        }

        // Zablokowane przejscie ma powiedziec, CZEGO brakuje — sama
        // informacja „brakuje okuc" nie mowi, co zamowic.
        $this->assertNotNull($blocked);
        $this->assertStringContainsString('uchwyt LUMINIS', (string) $blocked->blockedBy);
        $this->assertStringContainsString('potrzeba 3', (string) $blocked->blockedBy);
        $this->assertStringContainsString('na stanie 1', (string) $blocked->blockedBy);
    }

    #[Test]
    public function zlecenie_bez_okuc_nie_jest_blokowane(): void
    {
        $order = $this->order();

        // Pusta lista brakow to warunek spelniony, nie nierozstrzygniety.
        $this->assertSame([], $this->stock->shortages($order));
    }

    #[Test]
    public function rezerwacja_innego_zlecenia_nie_blokuje_wydania(): void
    {
        $product = $this->fitting();
        $this->ledger->receive($product, 5);

        $other = $this->order();
        $this->addFitting($other, $product, 5);
        $this->stock->reserve($other);

        $mine = $this->order();
        $this->addFitting($mine, $product, 5);

        // Braki liczy sie od stanu fizycznego. Zlecenie, ktore weszlo na
        // hale, nie ma przegrywac z obietnica zlozona komus innemu —
        // to osobny konflikt i osobna rozmowa.
        $this->assertSame([], $this->stock->shortages($mine));
    }

    #[Test]
    public function kompletnosc_okuc_mowi_to_samo_co_blokada_produkcji(): void
    {
        $rotula = $this->fitting('rotula 33 imbus');
        $zawias = $this->fitting('zawias 90');
        $this->ledger->receive($rotula, 10);
        $this->ledger->receive($zawias, 1);

        $full = $this->order();
        $this->addFitting($full, $rotula, 4);

        $partial = $this->order();
        $this->addFitting($partial, $rotula, 4);
        $this->addFitting($partial, $zawias, 4);

        $none = $this->order();
        $this->addFitting($none, $this->fitting('uchwyt'), 2);

        $bare = $this->order();

        $result = $this->stock->completeness([
            (int) $full->getKey(),
            (int) $partial->getKey(),
            (int) $none->getKey(),
            (int) $bare->getKey(),
        ]);

        // Komplet w kolumnie znaczy dokladnie: brak blokady „brakuje okuc"
        // przy przejsciu do produkcji. Dwie liczby o tym samym.
        $this->assertSame(100, $result[(int) $full->getKey()]['percent']);
        $this->assertSame([], $this->stock->shortages($full));

        // 4 z 4 rotul i 1 z 4 zawiasow: 5 z 8 sztuk.
        $this->assertSame(62, $result[(int) $partial->getKey()]['percent']);
        $this->assertSame(1, $result[(int) $partial->getKey()]['short']);
        $this->assertNotSame([], $this->stock->shortages($partial));

        $this->assertSame(0, $result[(int) $none->getKey()]['percent']);

        // Zlecenie bez okuc nie ma czego kompletowac — kreska, nie 100%.
        $this->assertArrayNotHasKey((int) $bare->getKey(), $result);
    }

    #[Test]
    public function wydane_okucia_to_komplet(): void
    {
        $product = $this->fitting();
        $order = $this->order();
        $this->ledger->receive($product, 4);
        $this->addFitting($order, $product, 4);

        $this->stock->reserve($order);
        $this->stock->issue($order);

        // Po wydaniu polka jest pusta, ale towar zszedl na to zlecenie —
        // stan po wydaniu mowi juz o innych.
        $this->assertSame(100, $this->stock->completeness([(int) $order->getKey()])[(int) $order->getKey()]['percent']);
    }
}
