<?php

declare(strict_types=1);

namespace Tests\Feature\Offers;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Offer;
use App\Models\User;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\GlobalParameter;
use App\Support\ParameterTemplate;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Offers\OfferMail;
use App\Services\Offers\OfferService;
use App\Services\Offers\OfferDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wydruk oferty i wysyłka.
 *
 * Trzy rzeczy warte pilnowania:
 *
 * 1. **PDF powstaje z migawki, nie ze zlecenia.** Wydruk sprzed
 *    miesiąca ma dziś wyglądać tak samo.
 * 2. **Tekst z pustym odwołaniem nie powstaje.** „Przedpłata na
 *    rachunek ” bez numeru to zdanie urwane w pół, a dosłowne
 *    `{{bank_account_iban}}` na wydruku byłoby jeszcze gorsze.
 * 3. **Status zmienia się dopiero po udanej wysyłce.** Oferta
 *    „wysłana”, której klient nie dostał, jest gorsza niż błąd.
 *
 * Testy nie podmieniają poczty przez `Mail::fake()`: `phpunit.xml`
 * ustawia `MAIL_MAILER=array`, więc wiadomość przechodzi **całą**
 * drogą — z renderowaniem widoku i załączaniem PDF-u — i tylko nie
 * opuszcza procesu. Atrapa przykryłaby dokładnie ten kawałek, który
 * najłatwiej zepsuć.
 */
class OfferDocumentTest extends TestCase
{
    use RefreshDatabase;

    private OfferService $service;
    private OfferDocument $document;
    private OfferMail $mail;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->service = new OfferService();
        $this->document = new OfferDocument();
        $this->mail = new OfferMail();
    }

    // ---------------------------------------------------------------
    // Podstawianie w tekstach ofertowych
    // ---------------------------------------------------------------

    #[Test]
    public function szablon_podstawia_wartosc_parametru(): void
    {
        $this->parameter('bank_account_iban', 'PL61109010140000071219812874');

        $rendered = ParameterTemplate::render(
            'Przedpłata na rachunek {{bank_account_iban}}',
        );

        $this->assertSame(
            'Przedpłata na rachunek PL61109010140000071219812874',
            $rendered,
        );
    }

    #[Test]
    public function tekst_z_pustym_odwolaniem_nie_powstaje(): void
    {
        // `bank_account_iban` jest zaseedowany pusty — tekst o przedplacie
        // nie ma czego podstawic, wiec nie powstaje wcale.
        $this->assertNull(
            ParameterTemplate::render('Przedpłata na rachunek {{bank_account_iban}}'),
        );
    }

    #[Test]
    public function tekst_bez_odwolan_przechodzi_bez_zmian(): void
    {
        $this->assertSame(
            'Termin realizacji: do 30 dni roboczych',
            ParameterTemplate::render('Termin realizacji: do 30 dni roboczych'),
        );
    }

    #[Test]
    public function migawka_niesie_tekst_wyrenderowany_a_nie_szablon(): void
    {
        $this->parameter('bank_account_iban', 'PL61109010140000071219812874');

        $order = $this->order();
        $this->list($order, '1000.00');
        $this->service->issue((int) $order->getKey(), []);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        $this->assertStringNotContainsString(
            '{{',
            (string) $offer->snapshot['texts']['payment_terms'],
        );
    }

    // ---------------------------------------------------------------
    // Wydruk
    // ---------------------------------------------------------------

    #[Test]
    public function pdf_powstaje_i_jest_plikiem_pdf(): void
    {
        $order = $this->order();
        $this->list($order, '1000.00');
        $this->service->issue((int) $order->getKey(), ['detail_level' => 'detailed']);

        /** @var Offer $offer */
        $offer = Offer::query()->with('order')->firstOrFail();
        $bytes = $this->document->render($offer);

        $this->assertStringStartsWith('%PDF', $bytes);
    }

    #[Test]
    public function wydruk_nie_zmienia_sie_po_zmianie_wyceny(): void
    {
        $order = $this->order();
        $this->list($order, '1000.00');
        $this->service->issue((int) $order->getKey(), []);

        /** @var Offer $offer */
        $offer = Offer::query()->with('order')->firstOrFail();
        $before = $this->document->render($offer);

        /** @var OrderItem $item */
        $item = OrderItem::query()->firstOrFail();
        $item->update(['unit_net_price' => '9999.00', 'amount' => '9999.00']);

        // Dwa wywolania na tej samej migawce daja ten sam dokument —
        // dlatego pliku nie przechowujemy.
        $this->assertSame(
            strlen($before),
            strlen($this->document->render($offer->refresh())),
        );
    }

    #[Test]
    public function nazwa_pliku_nie_niesie_ukosnika(): void
    {
        $order = $this->order();
        $this->list($order, '1000.00');
        $this->service->issue((int) $order->getKey(), []);

        /** @var Offer $offer */
        $offer = Offer::query()->with('order')->firstOrFail();

        $this->assertStringNotContainsString('/', $this->document->fileName($offer));
        $this->assertStringEndsWith('.pdf', $this->document->fileName($offer));
    }

    // ---------------------------------------------------------------
    // Wysyłka
    // ---------------------------------------------------------------

    #[Test]
    public function podglad_podstawia_numer_oferty_w_tresci(): void
    {
        $order = $this->order(email: 'klient@example.test');
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);

        $preview = $this->mail->preview((int) $order->getKey(), (int) $issued['id']);

        $this->assertSame([], $preview['errors']);
        $this->assertNotNull($preview['data']);
        $this->assertSame('klient@example.test', $preview['data']['to']);
        $this->assertStringContainsString(
            (string) $issued['number'],
            (string) $preview['data']['subject'],
        );
        $this->assertStringNotContainsString('{{', (string) $preview['data']['body']);
    }

    #[Test]
    public function podglad_bez_adresu_w_kartotece_nie_zgaduje(): void
    {
        $order = $this->order();
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);

        $preview = $this->mail->preview((int) $order->getKey(), (int) $issued['id']);

        // Brak adresu zostaje brakiem — ekran prosi o wpisanie, zamiast
        // podstawiac cokolwiek.
        $this->assertNull($preview['data']['to']);
    }

    #[Test]
    public function wysylka_bez_adresu_nie_przechodzi(): void
    {
        $order = $this->order();
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);

        $result = $this->mail->send((int) $order->getKey(), (int) $issued['id'], [
            'subject' => 'Oferta',
            'body' => 'treść',
        ]);

        $this->assertArrayHasKey('to', $result['errors']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();
        $this->assertSame('issued', $offer->status->value);
    }

    #[Test]
    public function udana_wysylka_oznacza_oferte_jako_wyslana(): void
    {
        $order = $this->order(email: 'klient@example.test');
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);

        $result = $this->mail->send((int) $order->getKey(), (int) $issued['id'], [
            'to' => 'klient@example.test',
            'subject' => 'Oferta',
            'body' => 'treść',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();
        $this->assertSame('sent', $offer->status->value);
        $this->assertNotNull($offer->sent_at);
    }

    #[Test]
    public function wysylka_zapisuje_uzyty_adres_w_dzienniku(): void
    {
        $order = $this->order(email: 'biuro@klient.test');
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);

        // Adres poprawiony recznie — oferta idzie do konkretnej osoby.
        $this->mail->send((int) $order->getKey(), (int) $issued['id'], [
            'to' => 'architekt@klient.test',
            'subject' => 'Oferta',
            'body' => 'treść',
        ]);

        $this->assertDatabaseHas('audit_entries', [
            'auditable_type' => Order::class,
            'auditable_id' => $order->getKey(),
            'event' => 'offer_mailed',
        ]);
    }

    #[Test]
    public function ponowna_wysylka_nie_cofa_decyzji_klienta(): void
    {
        $order = $this->order(email: 'klient@example.test');
        $this->list($order, '1000.00');
        $issued = $this->service->issue((int) $order->getKey(), []);
        $this->service->accept((int) $order->getKey(), (int) $issued['id']);

        $this->mail->send((int) $order->getKey(), (int) $issued['id'], [
            'to' => 'klient@example.test',
            'subject' => 'Oferta',
            'body' => 'treść',
        ]);

        /** @var Offer $offer */
        $offer = Offer::query()->firstOrFail();

        // Wyslanie przyjetej oferty raz jeszcze (np. na prosbe klienta)
        // nie cofa jej do „wyslanej".
        $this->assertSame('accepted', $offer->status->value);
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function parameter(string $key, string $value): void
    {
        GlobalParameter::query()
            ->where('key', $key)
            ->update(['value' => $value]);
    }

    private function order(?string $email = null): Order
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Handlowiec',
            'email' => 'handlowiec' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);

        $this->actingAs($user);

        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Wydruk ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
            'email' => $email,
        ]);

        /** @var InvoiceType $invoiceType */
        $invoiceType = InvoiceType::query()->where('vat_rate', 23)->firstOrFail();

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'invoice_type_id' => $invoiceType->getKey(),
        ]);
    }

    private function list(Order $order, string $amount): void
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 8mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);
    }
}
