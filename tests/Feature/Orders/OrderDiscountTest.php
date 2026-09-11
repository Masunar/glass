<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\OrderDiscount;
use App\Services\PriceListService;
use App\Models\ContractorPriceSection;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderValue;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\ProcessSeeder;
use App\Services\Orders\OrderDiscountService;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Rabat na zleceniu — czwarty poziom ceny.
 *
 * Dwie rzeczy są tu warte pilnowania: że rabat nie rusza kwot pozycji
 * (bo to zniszczyłoby ślad wyceny) i że limit roli obowiązuje po stronie
 * serwera, a nie tylko na ekranie.
 */
class OrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    private OrderDiscountService $service;
    private OrderValue $value;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();
        (new ProcessSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();
        (new GlobalParameterSeeder())->run();

        Order::query()->delete();

        $this->service = new OrderDiscountService();
        $this->value = new OrderValue();
    }

    private function actingAsRole(string $roleName): User
    {
        /** @var Role $role */
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Rabat',
            'email' => 'rabat' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Zlecenie z jedną pozycją szklaną o zadanej kwocie. Cena bierze się
     * z cennika, żeby test mówił o rabacie, a nie o wycenie.
     */
    private function order(string $amount = '1000.00'): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Rabat ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);

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

        return $order;
    }

    private function fresh(Order $order): Order
    {
        /** @var Order */
        return Order::query()
            ->with(['lists.items.processes', 'discounts', 'invoiceType'])
            ->findOrFail($order->getKey());
    }

    #[Test]
    public function rabat_obniza_sume_zlecenia(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        $this->assertSame('900.00', $this->value->totals($this->fresh($order))->net);
    }

    #[Test]
    public function rabat_nie_rusza_kwoty_pozycji(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->firstOrFail();

        // Kwota pozycji to snapshot wyceny. Gdyby rabat ja zmienial,
        // slad wyliczenia przestalby sie zgadzac z kwota.
        $this->assertSame('1000.00', $item->amount);
    }

    #[Test]
    public function podsumowanie_pokazuje_podstawe_rabat_i_kwote_po_rabacie(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertSame('1000.00', $totals->base);
        $this->assertSame('100.00', $totals->discount);
        $this->assertSame('900.00', $totals->net);
        $this->assertCount(1, $totals->sections);
        $this->assertSame(Section::GLASS->value, $totals->sections[0]['section']);
    }

    #[Test]
    public function rabat_dotyczy_tylko_swojej_sekcji(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::SERVICES->value,
            'name' => 'montaż',
            'quantity' => 1,
            'unit_net_price' => '500.00',
            'amount' => '500.00',
        ]);

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        // 1000 − 10 % = 900, usluga zostaje przy 500.
        $this->assertSame('1400.00', $this->value->totals($this->fresh($order))->net);
    }

    #[Test]
    public function brutto_liczy_sie_od_kwoty_po_rabacie(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);

        /** @var InvoiceType $type */
        $type = InvoiceType::query()->create([
            'name' => 'Testowy VAT ' . random_int(1000, 9999),
            'vat_rate' => 23,
        ]);

        $order = $this->order('1000.00');
        $order->update(['invoice_type_id' => $type->id]);

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertSame('900.00', $totals->net);
        $this->assertSame('207.00', $totals->vat);
        $this->assertSame('1107.00', $totals->gross);
    }

    #[Test]
    public function handlowiec_nie_przekroczy_swojego_limitu(): void
    {
        // Detaliczny podstawowy, szklo: administrator 100 %, starszy
        // handlowiec 15 %, handlowiec 10 % (PriceSectionSeeder).
        $this->actingAsRole(RoleSeeder::SALES);
        $order = $this->order('1000.00');

        $result = $this->service->save((int) $order->getKey(), [Section::GLASS->value => '25']);

        $this->assertArrayHasKey(Section::GLASS->value, $result['errors']);
        $this->assertSame('1000.00', $this->value->totals($this->fresh($order))->net);
    }

    #[Test]
    public function starszy_handlowiec_ma_wyzszy_limit(): void
    {
        $this->actingAsRole(RoleSeeder::SENIOR_SALES);
        $order = $this->order('1000.00');

        $this->assertSame([], $this->service->save(
            (int) $order->getKey(),
            [Section::GLASS->value => '15'],
        )['errors']);

        $this->assertSame('850.00', $this->value->totals($this->fresh($order))->net);
    }

    #[Test]
    public function limit_idzie_za_sekcja_cenowa_kontrahenta(): void
    {
        // Biznesowy strefa 3: handlowiec 0 %, wiec kazdy rabat odpada.
        $this->actingAsRole(RoleSeeder::SALES);
        $order = $this->order('1000.00');

        /** @var PriceSection $business */
        $business = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'Biznesowy strefa 3')
            ->firstOrFail();

        ContractorPriceSection::query()->create([
            'contractor_id' => $order->contractor_id,
            'section' => Section::GLASS->value,
            'price_section_id' => $business->id,
        ]);

        $result = $this->service->save(
            (int) $order->getKey(),
            [Section::GLASS->value => '5'],
        );

        $this->assertArrayHasKey(Section::GLASS->value, $result['errors']);
        $this->assertStringContainsString('Biznesowy strefa 3', $result['errors'][Section::GLASS->value][0]);
    }

    #[Test]
    public function uzytkownik_bez_roli_nie_da_zadnego_rabatu(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Bez',
            'last_name' => 'Roli',
            'email' => 'bezroli' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);
        $this->actingAs($user);

        $order = $this->order('1000.00');

        // Brak wiersza limitu to brak prawa do rabatu, nie brak
        // ograniczenia — inaczej nowe konto moze dac sto procent.
        $this->assertArrayHasKey(
            Section::GLASS->value,
            $this->service->save((int) $order->getKey(), [Section::GLASS->value => '1'])['errors'],
        );
    }

    #[Test]
    public function zwyzka_jest_odrzucona_do_czasu_decyzji(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $result = $this->service->save((int) $order->getKey(), [Section::GLASS->value => '-5']);

        $this->assertArrayHasKey(Section::GLASS->value, $result['errors']);
    }

    #[Test]
    public function rabat_zerowy_kasuje_wiersz_zamiast_zapisywac_zero(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);
        $this->assertSame(1, OrderDiscount::query()->where('order_id', $order->getKey())->count());

        $this->service->save((int) $this->fresh($order)->getKey(), [Section::GLASS->value => '0']);
        $this->assertSame(0, OrderDiscount::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function zmiana_rabatu_zostawia_slad_w_dzienniku(): void
    {
        $this->actingAsRole(RoleSeeder::ADMINISTRATOR);
        $order = $this->order('1000.00');

        $this->service->save((int) $order->getKey(), [Section::GLASS->value => '10']);

        $this->assertDatabaseHas('audit_entries', [
            'auditable_type' => Order::class,
            'auditable_id' => $order->getKey(),
            'event' => 'discount_changed',
        ]);
    }

    #[Test]
    public function ekran_podaje_limit_razem_z_rabatem(): void
    {
        $this->actingAsRole(RoleSeeder::SALES);
        $order = $this->order('1000.00');

        $rows = $this->service->board($this->fresh($order));
        $glass = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['section'] === Section::GLASS->value,
        ))[0];

        $this->assertSame('10.00', $glass['max_percent']);
        $this->assertSame('Detaliczny podstawowy', $glass['price_section']);
    }
}
