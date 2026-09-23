<?php

declare(strict_types=1);

namespace Tests\Feature\Offers;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Offer;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Offers\OfferService;
use App\Services\Offers\OfferDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Podgląd oferty przed wystawieniem.
 *
 * Pilnowane są trzy rzeczy, i wszystkie trzy są o tym, czego podgląd
 * **nie robi**: nie zapisuje, nie zużywa numeru i nie przepuszcza tego,
 * czego nie przepuściłoby wystawienie. Podgląd, który wolno więcej niż
 * zapisowi, pokazuje dokument, którego nie da się wystawić — czyli
 * kłamie dokładnie w tym momencie, w którym miał pomóc.
 */
class OfferPreviewTest extends TestCase
{
    use RefreshDatabase;

    private OfferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->service = new OfferService();
    }

    #[Test]
    public function podglad_nie_zapisuje_oferty(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $result = $this->service->preview($this->id($order), []);

        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['offer']);
        $this->assertFalse($result['offer']->exists);
        $this->assertSame(0, Offer::query()->count());
    }

    #[Test]
    public function podglad_nie_zuzywa_numeru(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->preview($this->id($order), []);
        $this->service->preview($this->id($order), []);

        $issued = $this->service->issue($this->id($order), []);

        // Numer powstaje przy wystawieniu. Gdyby podglad go pobieral,
        // pierwsza prawdziwa oferta zaczynalaby sie od trojki i w
        // numeracji zostawalaby dziura po dokumentach, ktore nie
        // istnieja.
        $this->assertSame($order->number . '/1', $issued['number']);
    }

    #[Test]
    public function podglad_odmawia_tam_gdzie_odmawia_wystawienie(): void
    {
        $order = $this->order(withInvoiceType: false);
        $this->list($order, 1, '1000.00');

        $preview = $this->service->preview($this->id($order), ['price_display' => 'gross']);
        $issue = $this->service->issue($this->id($order), ['price_display' => 'gross']);

        // Ten sam powod, z tej samej drogi. Rozne komunikaty znaczylyby,
        // ze podglad i zapis sprawdzaja dwie rozne rzeczy.
        $this->assertArrayHasKey('price_display', $preview['errors']);
        $this->assertSame($issue['errors'], $preview['errors']);
        $this->assertNull($preview['offer']);
    }

    #[Test]
    public function podglad_niesie_te_sama_migawke_co_wystawienie(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $preview = $this->service->preview($this->id($order), ['detail_level' => 'detailed']);
        $this->service->issue($this->id($order), ['detail_level' => 'detailed']);

        /** @var Offer $issued */
        $issued = Offer::query()->firstOrFail();

        // Wartosc podgladu polega na tym, ze to jest ten sam dokument.
        // Gdyby migawki sie roznily, podglad bylby druga implementacja
        // tej samej rzeczy — i rozjechalby sie przy pierwszej zmianie.
        $this->assertNotNull($preview['offer']);
        $this->assertSame($issued->snapshot, $preview['offer']->snapshot);
    }

    #[Test]
    public function wydruk_podgladu_jest_oznaczony_i_bez_numeru(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $preview = $this->service->preview($this->id($order), []);
        $this->assertNotNull($preview['offer']);

        $file = (new OfferDocument())->render($preview['offer'], true);

        // PDF nie jest tekstem, wiec nie szukamy w nim napisu. Wystarczy,
        // ze sie zlozyl: `number()` na niezapisanej ofercie wywalilby sie
        // na relacji, gdyby szablon o niego zapytal.
        $this->assertStringStartsWith('%PDF', $file);
        $this->assertStringContainsString(
            'podglad-oferty-',
            (new OfferDocument())->previewFileName($preview['offer']),
        );
    }

    #[Test]
    public function podglad_pustego_zlecenia_nie_powstaje(): void
    {
        $order = $this->order();
        $this->list($order, 1, null);

        $result = $this->service->preview($this->id($order), []);

        $this->assertArrayHasKey('offer', $result['errors']);
        $this->assertNull($result['offer']);
    }

    private function order(bool $withInvoiceType = true): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Podglad ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        $invoiceType = $withInvoiceType
            ? InvoiceType::query()->where('vat_rate', 23)->first()
            : null;

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'invoice_type_id' => $invoiceType?->getKey(),
        ]);
    }

    private function list(Order $order, int $number, ?string $amount): OrderList
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'role' => 'component',
            'is_included' => true,
        ]);

        if ($amount !== null) {
            OrderItem::query()->create([
                'order_list_id' => $list->id,
                'section' => Section::GLASS->value,
                'name' => 'float 8mm',
                'quantity' => 1,
                'unit_net_price' => $amount,
                'amount' => $amount,
            ]);
        }

        return $list;
    }

    private function id(Order $order): int
    {
        return (int) $order->getKey();
    }
}
