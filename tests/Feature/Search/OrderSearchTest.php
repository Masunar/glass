<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\SearchService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wyszukiwanie zleceń.
 *
 * Do tej pory zlecenia dało się znaleźć **wyłącznie po numerze**, więc
 * pytanie „co to było za zlecenie z balustradą dla tego pana z Gryfina"
 * nie miało odpowiedzi. Telefon działał, ale prowadził do kontrahenta,
 * nie do jego zleceń.
 */
class OrderSearchTest extends TestCase
{
    use RefreshDatabase;

    private SearchService $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->search = new SearchService();
        $this->actingAs($this->superuser());
    }

    #[Test]
    public function numer_dalej_znajduje_zlecenie(): void
    {
        $order = $this->order(91001);

        $this->assertContains('#91001', $this->titles((string) $order->number));
    }

    #[Test]
    public function znajduje_po_tresci_komentarza(): void
    {
        $this->order(91002, ['production_comment' => 'balustrada na taras, szkło z fazą']);

        $this->assertContains('#91002', $this->titles('balustrada'));
    }

    #[Test]
    public function trafienie_mowi_w_ktorym_polu_padlo(): void
    {
        // Bez tego wynik z komentarza wyglada na przypadkowy: numer
        // i klient nie tlumacza, dlaczego to zlecenie sie pokazalo.
        $this->order(91003, ['offer_comment' => 'klient prosi o wycenę wariantową']);

        $hit = $this->firstHit('wariantową');

        $this->assertNotNull($hit);
        $this->assertStringContainsString('komentarz do oferty', (string) $hit['subtitle']);
        $this->assertStringContainsString('wariantową', (string) $hit['subtitle']);
    }

    #[Test]
    public function znajduje_po_nazwie_kontrahenta(): void
    {
        $this->order(91004, [], 'Przedsiębiorstwo Szklarskie Kowalski');

        $this->assertContains('#91004', $this->titles('Kowalski'));
    }

    #[Test]
    public function znajduje_zlecenie_po_telefonie_kontrahenta(): void
    {
        // To jest cala tresc zgloszenia: telefon dzialal, ale prowadzil
        // do kartoteki, a nie do zlecen tego klienta.
        $this->order(91005, [], 'Nowak', '603 666 014');

        $this->assertContains('#91005', $this->titles('603666'));
    }

    #[Test]
    public function telefon_ze_spacjami_znajduje_sie_po_samych_cyfrach(): void
    {
        // Wada sprzed tej zmiany, nie tylko w zleceniach: numer lezy
        // w bazie tak, jak go wpisano, a szukanie porownywalo go
        // z samymi cyframi. Kartoteka miala to samo i nie miala testu.
        $this->order(91011, [], 'Gryfino Glass', '91 45 42 475');

        $this->assertContains('#91011', $this->titles('914542'));
    }

    #[Test]
    public function telefon_z_prefiksem_kraju_tez_sie_znajduje(): void
    {
        $this->order(91012, [], 'Zagranica', '+48 123 456 789');

        $this->assertContains('#91012', $this->titles('123456789'));
    }

    #[Test]
    public function kartoteka_kontrahentow_tez_szuka_po_cyfrach(): void
    {
        $this->order(91013, [], 'Kartoteka', '603 666 014');

        $found = false;

        foreach ($this->search->search('603666') as $group) {
            if ($group['key'] !== 'contractors') {
                continue;
            }

            /** @var list<array<string, mixed>> $hits */
            $hits = $group['hits'];
            $found = $hits !== [];
        }

        $this->assertTrue($found, 'Kontrahent nie znalazł się po samych cyfrach telefonu.');
    }

    #[Test]
    public function tresc_szuka_sie_dopiero_od_trzech_znakow(): void
    {
        // `LIKE '%xx%'` po dziesieciu kolumnach nie uzyje indeksu.
        $this->order(91006, ['short_note' => 'ekspresowo']);

        $this->assertNotContains('#91006', $this->titles('ek'));
        $this->assertContains('#91006', $this->titles('eks'));
    }

    #[Test]
    public function dokladny_numer_stoi_przed_trafieniem_w_tresc(): void
    {
        $this->order(91007);
        $this->order(91008, ['short_note' => 'dotyczy zlecenia 91007']);

        $titles = $this->titles('91007');

        $this->assertSame('#91007', $titles[0]);
        $this->assertContains('#91008', $titles);
    }

    #[Test]
    public function wycinek_nie_wraca_calego_komentarza(): void
    {
        // Podtytul ma zmiescic sie w jednej linii listy wynikow.
        $long = str_repeat('szkło hartowane z fazą polerowaną ', 12) . 'balustrada';

        $this->order(91009, ['production_comment' => $long]);

        $hit = $this->firstHit('balustrada');

        $this->assertNotNull($hit);
        $this->assertLessThan(160, mb_strlen((string) $hit['subtitle']));
    }

    #[Test]
    public function bez_uprawnienia_do_zlecen_grupa_w_ogole_nie_wraca(): void
    {
        $this->order(91010, ['short_note' => 'balustrada']);

        $this->actingAs($this->plainUser());

        $keys = array_column($this->search->search('balustrada'), 'key');

        $this->assertNotContains('orders', $keys);
    }

    /**
     * @return list<string>
     */
    private function titles(string $query): array
    {
        foreach ($this->search->search($query) as $group) {
            if ($group['key'] !== 'orders') {
                continue;
            }

            /** @var list<array<string, mixed>> $hits */
            $hits = $group['hits'];

            return array_map(static fn(array $hit): string => (string) $hit['title'], $hits);
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstHit(string $query): ?array
    {
        foreach ($this->search->search($query) as $group) {
            if ($group['key'] !== 'orders') {
                continue;
            }

            /** @var list<array<string, mixed>> $hits */
            $hits = $group['hits'];

            return $hits[0] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, string> $fields
     */
    private function order(
        int $number,
        array $fields = [],
        string $contractor = 'Klient testowy',
        ?string $phone = null,
    ): Order {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $party */
        $party = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => $contractor . ' ' . $number,
            'tax_id' => '8522347066',
            'phone' => $phone,
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => $number,
            'contractor_id' => $party->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            ...$fields,
        ]);

        OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        return $order;
    }

    private function superuser(): User
    {
        /** @var Role $role */
        $role = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $user = $this->user('admin');
        $user->assignRole($role);

        return $user;
    }

    /** Uzytkownik bez zadnej roli — nie widzi grupy zlecen. */
    private function plainUser(): User
    {
        return $this->user('bez-roli');
    }

    private function user(string $prefix): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Szukanie',
            'email' => $prefix . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);
    }
}
