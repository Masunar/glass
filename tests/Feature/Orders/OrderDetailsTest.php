<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\InvoiceType;
use App\Models\Status;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderDetailsService;
use App\Services\Orders\OrderCreditOverride;
use Database\Seeders\Core\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Termin i komentarze poprawiane z karty zlecenia.
 */
class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    private OrderDetailsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderDetailsService();
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Karta ' . random_int(1000, 9999),
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'client_deadline' => '2026-10-10',
        ]);
    }

    private function fresh(Order $order): Order
    {
        /** @var Order */
        return Order::query()->findOrFail($order->getKey());
    }

    #[Test]
    public function przesuniecie_zapisuje_sie_z_powodem_i_trafia_do_dziennika(): void
    {
        $order = $this->order();

        $result = $this->service->deadline((int) $order->getKey(), [
            'client_deadline' => '2026-10-10',
            'shifted_deadline' => '2026-10-17',
            'shift_reason' => 'brak szkła u dostawcy',
        ]);

        $fresh = $this->fresh($order);

        $this->assertSame([], $result['errors']);
        $this->assertSame('2026-10-10', $fresh->client_deadline?->toDateString());
        $this->assertSame('2026-10-17', $fresh->shifted_deadline?->toDateString());
        $this->assertSame('brak szkła u dostawcy', $fresh->shift_reason);

        /** @var AuditEntry $entry */
        $entry = AuditEntry::query()->where('event', 'deadline_changed')->firstOrFail();

        // Termin klienta sie nie zmienil, wiec w dzienniku go nie ma —
        // wpis mowi, co przesunieto, a nie przepisuje calego formularza.
        $this->assertSame(
            ['termin przesunięty', 'powód przesunięcia'],
            array_column($entry->changes ?? [], 'field'),
        );
    }

    #[Test]
    public function powod_bez_przesuniecia_nie_zostaje(): void
    {
        $order = $this->order();

        $this->service->deadline((int) $order->getKey(), [
            'client_deadline' => '2026-10-12',
            'shifted_deadline' => '',
            'shift_reason' => 'zostawiony z poprzedniej wersji',
        ]);

        // Powod bez przesuniecia bylby na karcie zdaniem o przesunieciu,
        // ktorego nie ma.
        $this->assertNull($this->fresh($order)->shift_reason);
        $this->assertSame('2026-10-12', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function zla_data_jest_odrzucana_i_niczego_nie_zmienia(): void
    {
        $order = $this->order();

        $result = $this->service->deadline((int) $order->getKey(), ['client_deadline' => '12.10.2026']);

        $this->assertArrayHasKey('client_deadline', $result['errors']);
        $this->assertSame('2026-10-10', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function komentarz_dla_produkcji_zapisuje_sie_a_pusty_czysci(): void
    {
        $order = $this->order();

        $this->assertSame([], $this->service->comment((int) $order->getKey(), 'production', '  fazę 10 mm  ')['errors']);
        $this->assertSame('fazę 10 mm', $this->fresh($order)->production_comment);

        $this->service->comment((int) $order->getKey(), 'production', '');

        // Pusty tekst to brak komentarza, nie komentarz z samych spacji.
        $this->assertNull($this->fresh($order)->production_comment);
        $this->assertSame(2, AuditEntry::query()->where('event', 'comment_changed')->count());
    }

    #[Test]
    public function krotka_uwaga_ma_limit_kolumny(): void
    {
        $order = $this->order();

        $result = $this->service->comment((int) $order->getKey(), 'short', str_repeat('a', 1001));

        $this->assertArrayHasKey('text', $result['errors']);
        $this->assertNull($this->fresh($order)->short_note);
    }

    #[Test]
    public function krotka_uwaga_miesci_tysiac_znakow_z_pogrubieniem(): void
    {
        $order = $this->order();
        // Gwiazdki od pogrubienia licza sie do limitu — sa w kolumnie.
        $text = '**pilne** ' . str_repeat('ą', 990);

        $result = $this->service->comment((int) $order->getKey(), 'short', $text);

        $this->assertSame([], $result['errors']);
        $this->assertSame($text, $this->fresh($order)->short_note);
    }

    #[Test]
    public function nieznany_komentarz_jest_odrzucany(): void
    {
        $order = $this->order();

        $this->assertArrayHasKey('field', $this->service->comment((int) $order->getKey(), 'accounting', 'x')['errors']);
    }

    private function person(bool $admin): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Anna',
            'last_name' => $admin ? 'Admin' : 'Handlowiec',
            'email' => 'zgoda' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        if ($admin) {
            /** @var Role $role */
            $role = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();
            $user->assignRole($role);
        }

        return $user->fresh() ?? $user;
    }

    #[Test]
    public function typ_faktury_ustawia_sie_z_karty_a_nabywca_jak_kontrahent_zostaje_pusty(): void
    {
        $order = $this->order();

        /** @var InvoiceType $type */
        $type = InvoiceType::query()->create(['name' => 'Typ karty ' . random_int(1000, 9999), 'vat_rate' => 23]);

        $result = $this->service->invoice((int) $order->getKey(), [
            'invoice_type_id' => $type->id,
            'buyer_same' => true,
            'buyer_name' => 'zostawione z poprzedniego wpisu',
        ]);

        $fresh = $this->fresh($order);

        $this->assertSame([], $result['errors']);
        $this->assertSame((int) $type->id, (int) $fresh->invoice_type_id);
        // „Dane jak kontrahent" to brak wlasnego nabywcy, a nie kopia
        // danych kontrahenta, ktora rozjedzie sie z kartoteka.
        $this->assertNull($fresh->buyer_name);
        $this->assertSame(1, AuditEntry::query()->where('event', 'invoice_changed')->count());
    }

    #[Test]
    public function inny_nabywca_wymaga_nazwy(): void
    {
        $order = $this->order();

        /** @var InvoiceType $type */
        $type = InvoiceType::query()->create(['name' => 'Typ nabywcy ' . random_int(1000, 9999), 'vat_rate' => 23]);

        $result = $this->service->invoice((int) $order->getKey(), [
            'invoice_type_id' => $type->id,
            'buyer_same' => false,
        ]);

        $this->assertArrayHasKey('buyer_name', $result['errors']);
    }

    #[Test]
    public function zgode_mimo_limitu_daje_tylko_administrator(): void
    {
        $order = $this->order();

        $this->actingAs($this->person(admin: false));
        $refused = (new OrderCreditOverride())->grant((int) $order->getKey(), 'stały klient');

        $this->assertArrayHasKey('reason', $refused['errors']);
        $this->assertNull($this->fresh($order)->credit_override_at);
    }

    #[Test]
    public function zgoda_wymaga_powodu_i_zostawia_slad(): void
    {
        $order = $this->order();
        $admin = $this->person(admin: true);
        $this->actingAs($admin);

        $service = new OrderCreditOverride();

        $this->assertArrayHasKey('reason', $service->grant((int) $order->getKey(), '  ')['errors']);
        $this->assertSame([], $service->grant((int) $order->getKey(), 'płaci po montażu')['errors']);

        $fresh = $this->fresh($order);

        // Kto i dlaczego — na zleceniu i w dzienniku, nie tylko w pamieci
        // osoby, ktora kliknela.
        $this->assertSame((int) $admin->getKey(), $fresh->credit_override_by);
        $this->assertSame('płaci po montażu', $fresh->credit_override_reason);
        $this->assertSame(1, AuditEntry::query()->where('event', 'credit_override_granted')->count());

        $service->revoke((int) $order->getKey());

        $this->assertNull($this->fresh($order)->credit_override_at);
        $this->assertSame(1, AuditEntry::query()->where('event', 'credit_override_revoked')->count());
    }
}
