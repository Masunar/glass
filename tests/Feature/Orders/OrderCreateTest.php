<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Status;
use App\Models\Location;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\Orders\OrderService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Zakładanie zlecenia.
 *
 * Wymagane są dwie rzeczy: kontrahent i sposób wydania wraz z miejscem,
 * do którego towar pojedzie. Reszta jest do uzupełnienia później —
 * formularz żądający kompletu na starcie uczy wpisywania daty z sufitu.
 */
class OrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();

        Order::query()->delete();

        $this->service = new OrderService();
    }

    private function contractor(): Contractor
    {
        /** @var Contractor */
        return Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Zakladanie ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);
    }

    private function pickupPoint(): Location
    {
        /** @var Location */
        return Location::query()->where('is_pickup_point', true)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return [
            'contractor_id' => $this->contractor()->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'pickup_location_id' => $this->pickupPoint()->id,
            ...$overrides,
        ];
    }

    #[Test]
    public function zlecenie_dostaje_status_poczatkowy_z_katalogu(): void
    {
        $result = $this->service->create($this->input());

        $this->assertSame([], $result['errors']);

        /** @var Order $order */
        $order = Order::query()->findOrFail($result['id']);

        /** @var Status $expected */
        $expected = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        $this->assertSame((int) $expected->getKey(), $order->status_id);
    }

    #[Test]
    public function zlecenie_dostaje_od_razu_pierwsza_liste(): void
    {
        $result = $this->service->create($this->input());

        /** @var Order $order */
        $order = Order::query()->with('lists')->findOrFail($result['id']);

        $this->assertCount(1, $order->lists);
        $this->assertSame(1, $order->lists[0]->number);
        $this->assertTrue($order->lists[0]->is_included);
    }

    #[Test]
    public function numery_kolejnych_zlecen_nie_powtarzaja_sie(): void
    {
        $first = $this->service->create($this->input());
        $second = $this->service->create($this->input());

        $this->assertNotSame($first['number'], $second['number']);
    }

    #[Test]
    public function odbior_wlasny_bez_punktu_jest_odrzucony(): void
    {
        $result = $this->service->create($this->input(['pickup_location_id' => null]));

        $this->assertArrayHasKey('pickup_location_id', $result['errors']);
        $this->assertNull($result['id']);
    }

    #[Test]
    public function dowoz_bez_adresu_jest_odrzucony(): void
    {
        $result = $this->service->create($this->input([
            'delivery_method' => DeliveryMethod::DELIVERY->value,
            'pickup_location_id' => null,
        ]));

        $this->assertArrayHasKey('delivery_address', $result['errors']);
    }

    #[Test]
    public function punkt_odbioru_musi_wydawac_towar(): void
    {
        /** @var Location $hall */
        $hall = Location::query()->create([
            'name' => 'Hala bez wydania',
            'is_pickup_point' => false,
            'is_active' => true,
        ]);

        $result = $this->service->create($this->input([
            'pickup_location_id' => $hall->id,
        ]));

        $this->assertSame(
            ['To miejsce nie wydaje towaru.'],
            $result['errors']['pickup_location_id'],
        );
    }

    #[Test]
    public function przy_montazu_punkt_odbioru_nie_zostaje_zapisany(): void
    {
        $result = $this->service->create($this->input([
            'delivery_method' => DeliveryMethod::INSTALLATION->value,
            'delivery_address' => 'ul. Radisson Pazim, Szczecin',
        ]));

        /** @var Order $order */
        $order = Order::query()->findOrFail($result['id']);

        // Punkt odbioru ma sens wylacznie przy odbiorze wlasnym — przy
        // montazu jedziemy do klienta, wiec pole musi zostac puste,
        // nawet jesli formularz przyslal wartosc.
        $this->assertNull($order->pickup_location_id);
        $this->assertSame('ul. Radisson Pazim, Szczecin', $order->delivery_address);
    }

    #[Test]
    public function nieistniejacy_kontrahent_jest_odrzucony(): void
    {
        $result = $this->service->create($this->input(['contractor_id' => 999999]));

        $this->assertArrayHasKey('contractor_id', $result['errors']);
    }

    #[Test]
    public function zalozenie_zostawia_slad_w_dzienniku(): void
    {
        $result = $this->service->create($this->input());

        /** @var AuditEntry $entry */
        $entry = AuditEntry::query()
            ->where('auditable_type', Order::class)
            ->where('auditable_id', $result['id'])
            ->firstOrFail();

        $this->assertSame('created', $entry->event);
    }

    #[Test]
    public function termin_i_typ_faktury_sa_opcjonalne(): void
    {
        $result = $this->service->create($this->input());

        $this->assertSame([], $result['errors']);

        /** @var Order $order */
        $order = Order::query()->findOrFail($result['id']);

        $this->assertNull($order->client_deadline);
        $this->assertNull($order->invoice_type_id);
    }

    #[Test]
    public function slowniki_formularza_oddaja_tylko_miejsca_wydajace_towar(): void
    {
        Location::query()->create([
            'name' => 'Magazyn zamkniety',
            'is_pickup_point' => false,
            'is_active' => true,
        ]);

        $options = $this->service->formOptions();

        $pickupNames = array_column($options['pickup_points'], 'name');
        $branchNames = array_column($options['branches'], 'name');

        $this->assertNotContains('Magazyn zamkniety', $pickupNames);
        $this->assertContains('Magazyn zamkniety', $branchNames);
    }
}
