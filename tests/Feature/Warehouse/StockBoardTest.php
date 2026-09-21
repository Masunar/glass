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
use App\Services\Warehouse\StockBoard;
use App\Services\Warehouse\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ekran magazynu.
 *
 * Najważniejszy test w tym pliku to ten o duplikatach. Stary ekran
 * „Okucia w zamówieniach" pokazywał pozycje po kilka razy, a służy do
 * decyzji zakupowych — zawyżone zapotrzebowanie to zamówienie większe
 * niż potrzeba, u dostawcy i w hartowni (`40-magazyn.md` §5).
 */
class StockBoardTest extends TestCase
{
    use RefreshDatabase;

    private StockBoard $board;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->board = new StockBoard();
        $this->ledger = new StockLedger();
    }

    private function fitting(string $code, string $name = 'okucie'): Product
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
            'code' => $code,
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Zapotrzebowanie ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);
    }

    private function listWith(Order $order, int $number, Product $product, float $quantity): void
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'role' => 'component',
            'is_included' => true,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'product_id' => $product->id,
            'section' => Section::FITTINGS->value,
            'name' => $product->name,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit_net_price' => '10.00',
            'amount' => number_format($quantity * 10, 2, '.', ''),
        ]);
    }

    #[Test]
    public function dwie_listy_nie_mnoza_zapotrzebowania(): void
    {
        $product = $this->fitting('DPW12');
        $order = $this->order();

        // Dokladnie ten uklad, ktory w starym systemie dawal wiersz
        // dwa razy: jedno zlecenie, dwie listy, ta sama pozycja.
        $this->listWith($order, 1, $product, 3);
        $this->listWith($order, 2, $product, 2);

        $rows = $this->board->demand()['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame(5.0, $rows[0]['needed']);
    }

    #[Test]
    public function braki_ida_na_gore(): void
    {
        $have = $this->fitting('JBP-3000 AL', 'jest na stanie');
        $missing = $this->fitting('JBP-3000 PBC', 'brakuje');

        $this->ledger->receive($have, 10);

        $order = $this->order();
        $this->listWith($order, 1, $have, 2);
        $this->listWith($order, 2, $missing, 4);

        $rows = $this->board->demand()['rows'];

        // Ekran sluzy do decyzji zakupowych, wiec zaczyna od tego,
        // czego nie ma.
        $this->assertSame('brakuje', $rows[0]['name']);
        $this->assertSame(4.0, $rows[0]['missing']);
        $this->assertSame(0.0, $rows[1]['missing']);
    }

    #[Test]
    public function sugestia_zakupowa_jest_na_liscie_stanow(): void
    {
        $product = $this->fitting('TGHU90LH BL');
        $this->ledger->thresholds($product, min: 2, max: 12);
        $this->ledger->receive($product, 1);

        $board = $this->board->levels();

        $this->assertSame(11.0, $board['rows'][0]['to_order']);
        $this->assertSame(1, $board['summary']['to_order']);
    }

    #[Test]
    public function filtr_brakow_zostawia_tylko_to_co_trzeba_zamowic(): void
    {
        $short = $this->fitting('TGHU90LH BL', 'ponizej progu');
        $this->ledger->thresholds($short, min: 2, max: 12);

        $full = $this->fitting('TGHU90LH PC', 'wystarczy');
        $this->ledger->thresholds($full, min: 2, max: 12);
        $this->ledger->receive($full, 6);

        $board = $this->board->levels(shortagesOnly: true);

        $this->assertCount(1, $board['rows']);
        $this->assertSame('ponizej progu', $board['rows'][0]['name']);
        // Licznik w podsumowaniu liczy caly magazyn, nie przefiltrowana
        // liste — inaczej filtr zmienialby odpowiedz na pytanie
        // „ile pozycji czeka na zamowienie".
        $this->assertSame(1, $board['summary']['to_order']);
    }

    #[Test]
    public function zlecenia_zamkniete_nie_generuja_zapotrzebowania(): void
    {
        $product = $this->fitting('DPW12');

        /** @var Status $archive */
        $archive = Status::findByCode(StatusDomain::ORDER, 'ARCHIWUM');

        $order = $this->order();
        $this->listWith($order, 1, $product, 3);
        $order->status_id = $archive->id;
        $order->save();

        // Zamkniete zlecenie nie potrzebuje juz niczego zamawiac.
        $this->assertSame([], $this->board->demand()['rows']);
    }
}
