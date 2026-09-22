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
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Oferta jako zapisany dokument.
 *
 * Dwie rzeczy są tu warte pilnowania, bo obie dotyczą tego, co klient
 * dostał na papierze:
 *
 * 1. **Migawka jest zamrożona.** Zlecenie żyje dalej, oferta nie.
 *    Gdyby kwota oferty szła za wyceną, historia mówiłaby o dzisiejszym
 *    stanie zlecenia, a nie o tym, co poszło.
 * 2. **Suma pomija warianty.** Klient nie płaci za wszystkie propozycje
 *    naraz, a rola listy pamięta o tym zawsze — inaczej niż człowiek.
 */
class OfferTest extends TestCase
{
    use RefreshDatabase;

    private OfferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->service = new OfferService();
    }

    // ---------------------------------------------------------------
    // Numer i wersja
    // ---------------------------------------------------------------

    #[Test]
    public function numer_sklada_sie_z_numeru_zlecenia_i_kolejnego(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $first = $this->service->issue($this->id($order), []);
        $second = $this->service->issue($this->id($order), []);

        $this->assertSame($order->number . '/1', $first['number']);
        $this->assertSame($order->number . '/2', $second['number']);
    }

    #[Test]
    public function kolejne_wystawienie_nie_kasuje_poprzedniego(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), []);
        $this->service->issue($this->id($order), []);

        // Poprawka to nastepne wystawienie, a nie edycja. Gdyby stara
        // wersja znikala, nie byloby czym udowodnic, co klient dostal.
        $this->assertSame(2, Offer::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function zlecenie_bez_pozycji_nie_da_sie_zaofertowac(): void
    {
        $order = $this->order();
        $this->list($order, 1, null);

        $result = $this->service->issue($this->id($order), []);

        $this->assertArrayHasKey('offer', $result['errors']);
        $this->assertNull($result['id']);
    }

    // ---------------------------------------------------------------
    // Migawka
    // ---------------------------------------------------------------

    #[Test]
    public function kwota_oferty_nie_zmienia_sie_po_zmianie_wyceny(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), []);

        /** @var OrderItem $item */
        $item = OrderItem::query()->firstOrFail();
        $item->update(['unit_net_price' => '9999.00', 'amount' => '9999.00']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        $this->assertSame('1000.00', $offer->net);
        $this->assertSame('1000.00', $offer->snapshot['totals']['net']);
    }

    #[Test]
    public function nieszczegolowa_nie_zapisuje_pozycji(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), ['detail_level' => 'summary']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        // Migawka ma byc tym, co poszlo, a nie tym, co moglo pojsc.
        $this->assertSame([], $offer->snapshot['lists'][0]['items']);
    }

    #[Test]
    public function szczegolowa_zapisuje_pozycje_z_kwotami(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), ['detail_level' => 'detailed']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();
        $items = $offer->snapshot['lists'][0]['items'];

        $this->assertCount(1, $items);
        $this->assertSame('1000.00', $items[0]['amount']);
    }

    #[Test]
    public function migawka_niesie_teksty_ofertowe_ze_slownika(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), []);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        // Parametr zmieniony w marcu nie ma prawa zmienic oferty z lutego.
        $this->assertArrayHasKey('payment_terms', $offer->snapshot['texts']);
        $this->assertArrayHasKey('name', $offer->snapshot['seller']);
    }

    // ---------------------------------------------------------------
    // Suma i warianty
    // ---------------------------------------------------------------

    #[Test]
    public function suma_domyslnie_pomija_warianty(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $this->list($order, 2, '400.00', alternative: true);

        $this->service->issue($this->id($order), []);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        // Alternatywa jest na ofercie widoczna, ale nie w sumie —
        // klient nie placi za obie propozycje naraz.
        $this->assertSame('1000.00', $offer->snapshot['sum']['net']);
        $this->assertCount(2, $offer->snapshot['lists']);
    }

    #[Test]
    public function suma_wszystkiego_dolicza_warianty(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $this->list($order, 2, '400.00', alternative: true);

        $this->service->issue($this->id($order), ['sum_mode' => 'all']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        $this->assertSame('1400.00', $offer->snapshot['sum']['net']);
        // Brutto zostaje nieznane: alternatywy nie maja policzonego
        // VAT-u, bo nie wchodza do kwoty zlecenia.
        $this->assertNull($offer->snapshot['sum']['gross']);
    }

    #[Test]
    public function bez_sumy_nie_ma_czego_pokazac(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');

        $this->service->issue($this->id($order), ['sum_mode' => 'none']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        $this->assertFalse($offer->snapshot['sum']['is_shown']);
        $this->assertNull($offer->snapshot['sum']['net']);
    }

    #[Test]
    public function wariantowosc_zapisuje_sie_w_chwili_wystawienia(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $this->list($order, 2, '400.00', alternative: true);

        $this->service->issue($this->id($order), []);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();
        $this->assertTrue($offer->is_variant);

        // Role list moga sie pozniej zmienic — pytanie brzmi „czy **ta**
        // oferta byla wariantowa", wiec odpowiedz zostaje przy niej.
        OrderList::query()->where('number', 2)->update(['role' => 'component']);
        $this->assertTrue($offer->refresh()->is_variant);
    }

    // ---------------------------------------------------------------
    // Brutto wymaga znanej stawki
    // ---------------------------------------------------------------

    #[Test]
    public function oferta_brutto_bez_typu_faktury_jest_odrzucana(): void
    {
        $order = $this->order(withInvoiceType: false);
        $this->list($order, 1, '1000.00');

        $result = $this->service->issue($this->id($order), ['price_display' => 'gross']);

        // Lepiej zatrzymac sie tutaj niz wyslac klientowi kwote, ktorej
        // nikt nie policzyl.
        $this->assertArrayHasKey('price_display', $result['errors']);
    }

    #[Test]
    public function oferta_netto_bez_typu_faktury_przechodzi(): void
    {
        $order = $this->order(withInvoiceType: false);
        $this->list($order, 1, '1000.00');

        $result = $this->service->issue($this->id($order), ['price_display' => 'net']);

        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['id']);
    }

    // ---------------------------------------------------------------
    // Decyzja klienta
    // ---------------------------------------------------------------

    #[Test]
    public function przyjecie_wariantu_wlacza_go_i_wylacza_pozostale(): void
    {
        $order = $this->order();
        $room = $this->list($order, 1, '1000.00');
        $first = $this->list($order, 2, '400.00', alternative: true, included: true);
        $second = $this->list($order, 3, '500.00', alternative: true);

        $issued = $this->service->issue($this->id($order), []);
        $this->service->accept($this->id($order), (int) $issued['id'], (int) $second->getKey());

        // Wskazany wariant wchodzi, konkurencyjny wypada, pomieszczenie
        // zostaje nietkniete — wybor miedzy szklem 8 a 6 mm nie jest
        // wyborem miedzy kuchnia a lazienka.
        $this->assertTrue($second->refresh()->is_included);
        $this->assertFalse($first->refresh()->is_included);
        $this->assertTrue($room->refresh()->is_included);
    }

    #[Test]
    public function przyjecie_zapisuje_ktory_wariant_wybrano(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $variant = $this->list($order, 2, '400.00', alternative: true);

        $issued = $this->service->issue($this->id($order), []);
        $this->service->accept($this->id($order), (int) $issued['id'], (int) $variant->getKey());

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        $this->assertSame((int) $variant->getKey(), $offer->accepted_list_id);
        $this->assertNotNull($offer->decided_at);
    }

    #[Test]
    public function odrzucenie_bez_powodu_nie_przechodzi(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $issued = $this->service->issue($this->id($order), []);

        $result = $this->service->reject($this->id($order), (int) $issued['id'], []);

        // Bez powodu po pol roku nie da sie powiedziec, czy przegrywamy
        // cena, czy terminem.
        $this->assertArrayHasKey('rejection_reason', $result['errors']);
    }

    #[Test]
    public function rozstrzygnietej_oferty_nie_da_sie_rozstrzygnac_drugi_raz(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $issued = $this->service->issue($this->id($order), []);

        $this->service->reject($this->id($order), (int) $issued['id'], [
            'rejection_reason' => 'za drogo',
        ]);

        $result = $this->service->accept($this->id($order), (int) $issued['id']);

        $this->assertArrayHasKey('offer', $result['errors']);
    }

    #[Test]
    public function oferta_z_innego_zlecenia_nie_przechodzi(): void
    {
        $first = $this->order();
        $this->list($first, 1, '1000.00');
        $issued = $this->service->issue($this->id($first), []);

        $second = $this->order();
        $this->list($second, 1, '500.00');

        $result = $this->service->markSent($this->id($second), (int) $issued['id']);

        $this->assertArrayHasKey('offer', $result['errors']);
    }

    #[Test]
    public function status_oferty_nie_rusza_statusu_zlecenia(): void
    {
        $order = $this->order();
        $this->list($order, 1, '1000.00');
        $before = (int) $order->status_id;

        $issued = $this->service->issue($this->id($order), []);
        $this->service->reject($this->id($order), (int) $issued['id'], [
            'rejection_reason' => 'klient wybral konkurencje',
        ]);

        // Klient moze odrzucic wariant i poprosic o drugi — zlecenie ma
        // wtedy zyc dalej.
        $this->assertSame($before, (int) $order->refresh()->status_id);
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function order(bool $withInvoiceType = true): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Oferta ' . random_int(1000, 9999),
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

    private function list(
        Order $order,
        int $number,
        ?string $amount,
        bool $alternative = false,
        bool $included = false,
    ): OrderList {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'role' => $alternative ? 'alternative' : 'component',
            // Alternatywa zaklada sie wylaczona z kwoty; test wlacza ja
            // jawnie tam, gdzie sprawdza przelaczanie wariantow.
            'is_included' => !$alternative || $included,
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
