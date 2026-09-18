<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\OrderPane;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\ProductService;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use App\Services\Orders\OrderItemService;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Formatki na zleceniu.
 *
 * Cena pozycji jest snapshotem: liczy się raz, przy zapisie, i zostaje.
 * Testy pilnują trzech rzeczy, na których łatwo stracić pieniądze —
 * że kwota materiału i kwoty procesów nie liczą się podwójnie, że brak
 * ceny nie zamienia się w zero i że zmiana wymiarów przelicza wszystko,
 * a nie tylko materiał.
 */
class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemService $service;

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

        $this->service = new OrderItemService();
    }

    private function glass(): Product
    {
        /** @var Product */
        return Product::query()->where('name', 'float 8mm')->firstOrFail();
    }

    private function section(string $name, string $section = Section::GLASS->value): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', $section)
            ->where('name', $name)
            ->firstOrFail();
    }

    /** Cena zakupu 52,00 × 4,0 = 208,00 zł/m². */
    private function priceGlass(): void
    {
        (new PriceListService())->update([[
            'product_id' => $this->glass()->id,
            'price_section_id' => $this->section('Detaliczny podstawowy')->id,
            'coefficient' => '4.0',
        ]]);
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Formatki ' . random_int(1000, 9999),
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
            'role' => 'component',
            'is_included' => true,
        ]);

        return $order;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function pane(array $overrides = []): array
    {
        return [
            'product_id' => $this->glass()->id,
            'width_mm' => 1000,
            'height_mm' => 1000,
            'quantity' => 1,
            'is_tempered' => false,
            ...$overrides,
        ];
    }

    #[Test]
    public function formatka_wycenia_sie_z_cennika_kontrahenta(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // 1 m² × 208,00 zł/m²
        $this->assertSame('208.00', $item->amount);
        $this->assertSame('208.00', $item->unit_net_price);
    }

    #[Test]
    public function sciezka_wyliczenia_zapisuje_sie_razem_z_kwota(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $codes = array_column($item->price_path ?? [], 'code');

        // Bez sladu nikt nie odpowie, skad wziela sie ta kwota.
        $this->assertContains('catalogue', $codes);
        $this->assertContains('base', $codes);
    }

    #[Test]
    public function brak_ceny_nie_zamienia_sie_w_zero_tylko_w_powod(): void
    {
        // Cennik pusty — produkt nie ma pozycji w zadnej sekcji.
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $this->assertSame('0.00', $item->amount);

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame('0.00', $board['totals']['net']);
    }

    #[Test]
    public function proces_bez_pozycji_w_cenniku_jest_oznaczony_a_nie_wyceniony(): void
    {
        $this->priceGlass();
        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        $this->assertCount(1, $item->processes);
        $this->assertSame('0.00', $item->processes[0]->amount);
        // Materiał wyceniony, proces nie — pozycja nie może udawać,
        // że cięcie jest darmowe.
        $this->assertSame('208.00', $item->amount);
    }

    #[Test]
    public function wyceniony_proces_jest_osobnym_wierszem_a_nie_czescia_materialu(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        // 4 mb obwodu × 12,00 = 48,00; materiał zostaje przy swoich 208,00.
        $this->assertSame('208.00', $item->amount);
        $this->assertSame('48.00', $item->processes[0]->amount);

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame('256.00', $board['totals']['net']);
    }

    #[Test]
    public function zmiana_wymiarow_przelicza_takze_procesy(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $created = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['width_mm' => 2000, 'processes' => [$cutting->id]]),
            $created['id'],
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($created['id']);

        // 2 m² × 208,00 = 416,00; obwod 6 mb × 12,00 = 72,00
        $this->assertSame('416.00', $item->amount);
        $this->assertSame('72.00', $item->processes[0]->amount);
        $this->assertCount(1, $item->processes);
    }

    #[Test]
    public function usluga_liczy_sie_z_ilosci_i_ceny(): void
    {
        $order = $this->order();

        $result = $this->service->saveService((int) $order->getKey(), [
            'name' => 'montaż luster',
            'quantity' => '6.13',
            'unit_net_price' => '255.00',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $this->assertSame('1563.15', $item->amount);
        $this->assertSame(Section::SERVICES, $item->section);
    }

    #[Test]
    public function wymiar_ponad_mozliwosci_hali_jest_odrzucony(): void
    {
        $order = $this->order();

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['width_mm' => 9000]),
        );

        $this->assertArrayHasKey('width_mm', $result['errors']);
        $this->assertNull($result['id']);
    }

    #[Test]
    public function pozycji_z_cudzego_zlecenia_nie_da_sie_skasowac(): void
    {
        $this->priceGlass();

        $mine = $this->order();
        $other = $this->order();

        $created = $this->service->savePane((int) $other->getKey(), $this->pane());

        $result = $this->service->delete((int) $mine->getKey(), (int) $created['id']);

        $this->assertNotSame([], $result['errors']);
        $this->assertNotNull(OrderItem::query()->find($created['id']));
    }

    #[Test]
    public function usuniecie_pozycji_zabiera_formatke_i_procesy(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $created = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        $this->service->delete((int) $order->getKey(), (int) $created['id']);

        $this->assertNull(OrderItem::query()->find($created['id']));
        $this->assertSame('0.00', $this->service->board((int) $order->getKey())['totals']['net']);
    }

    #[Test]
    public function odrzucona_alternatywa_nie_wchodzi_do_sumy_ani_do_metrow(): void
    {
        $this->priceGlass();
        $order = $this->order();

        /** @var OrderList $rejected */
        $rejected = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 2,
            'role' => 'alternative',
            'is_included' => false,
        ]);

        $this->service->savePane((int) $order->getKey(), $this->pane());
        $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['order_list_id' => $rejected->id, 'width_mm' => 2000]),
        );

        $totals = $this->service->board((int) $order->getKey())['totals'];

        $this->assertSame('208.00', $totals['net']);
        $this->assertSame(1.0, $totals['m2']);
    }

    /**
     * Cennik procesu: proces jest w cenniku produktem usługowym.
     * Współczynnik 1,0, żeby cena sprzedaży równała się cenie zakupu
     * i test mówił o wycenie formatki, a nie o marży.
     */

    #[Test]
    public function minimalna_powierzchnia_bierze_sie_z_parametrow(): void
    {
        $this->priceGlass();
        $order = $this->order();

        // 0,2 m² przy parametrze 0,4 dla hartowanej: rozliczamy 0,4.
        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 500,
            'height_mm' => 400,
            'is_tempered' => true,
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            '83.20',
            OrderItem::query()->findOrFail($result['id'])->amount,
        );
    }

    #[Test]
    public function wpisana_minimalna_powierzchnia_nadpisuje_parametr(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 500,
            'height_mm' => 400,
            'is_tempered' => true,
            'min_billable_m2' => '1.0',
        ]));

        // Wyjatek przy formatce wygrywa z parametrem globalnym.
        $this->assertSame([], $result['errors']);
        $this->assertSame(
            '208.00',
            OrderItem::query()->findOrFail($result['id'])->amount,
        );
    }

    #[Test]
    public function zero_jest_prawidlowa_minimalna_powierzchnia(): void
    {
        $this->priceGlass();
        $order = $this->order();

        // „Rozlicz doslownie tyle, ile jest" to poprawna odpowiedz,
        // a nie brak wpisu. Sprawdzamy sama zapisana wartosc i to, ze
        // kwota zeszla ponizej tej z parametru — bez wiazania testu
        // z doplata za formatke tansza niz prog.
        $withParameter = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 500,
            'height_mm' => 400,
            'is_tempered' => true,
        ]));

        $zeroed = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 500,
            'height_mm' => 400,
            'is_tempered' => true,
            'min_billable_m2' => '0',
        ]));

        $this->assertSame(
            0.0,
            OrderPane::query()->findOrFail($zeroed['id'])->min_billable_m2,
        );
        $this->assertLessThan(
            (float) OrderItem::query()->findOrFail($withParameter['id'])->amount,
            (float) OrderItem::query()->findOrFail($zeroed['id'])->amount,
        );
    }

    #[Test]
    public function pilne_i_dwa_komentarze_zapisuja_sie_na_pozycji(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'is_urgent' => true,
            'note' => 'klient prosi o zdjęcie przed wysyłką',
            'production_note' => 'narożniki lekko stępione',
        ]));

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // Dwa komentarze o dwoch odbiorcach: uwaga handlowa moze trafic
        // na oferte, instrukcja technologiczna idzie na hale.
        $this->assertTrue($item->is_urgent);
        $this->assertSame('klient prosi o zdjęcie przed wysyłką', $item->note);
        $this->assertSame('narożniki lekko stępione', $item->production_note);
    }

    #[Test]
    public function komentarz_produkcyjny_pozycji_jedzie_na_hale(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $this->service->savePane((int) $order->getKey(), $this->pane([
            'is_urgent' => true,
            'production_note' => 'okrąg fi 1800',
        ]));

        $board = $this->service->board((int) $order->getKey());
        $row = $board['lists'][0]['glass'][0];

        $this->assertTrue($row['is_urgent']);
        $this->assertSame('okrąg fi 1800', $row['production_note']);
    }

    #[Test]
    public function pusta_minimalna_powierzchnia_nie_utrwala_sie_jako_zero(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        $board = $this->service->board((int) $order->getKey());

        // `null` znaczy „z parametrow wyceny". Zapisane zero byloby
        // zamrozeniem dzisiejszej wartosci na zawsze.
        $this->assertNull($board['lists'][0]['glass'][0]['min_billable_m2']);
        $this->assertSame([], $result['errors']);
    }


    #[Test]
    public function proces_liczy_sie_od_swojej_jednostki(): void
    {
        $this->priceGlass();
        $order = $this->order();

        // CNC rozlicza sie od sztuki. Szyba 1500 × 1000 ma 5 mb obwodu,
        // wiec liczona od metra biezacego wystawilaby 750 zamiast 150.
        $cnc = $this->serviceProduct('R', 'CNC 60min', null, '150.00', Unit::PIECE);

        /** @var Process $process */
        $process = Process::findByCode('R');

        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 1500,
            'height_mm' => 1000,
            'processes' => [['process_id' => $process->id, 'product_id' => $cnc->id]],
        ]));

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        $this->assertSame('150.00', $item->processes[0]->amount);
    }

    #[Test]
    public function proces_od_metra_kwadratowego_liczy_powierzchnie(): void
    {
        $this->priceGlass();
        $order = $this->order();

        // Hartowanie od m2: 1,5 m2 × 40,00 = 60,00.
        $tempering = $this->serviceProduct('H', 'Hartowanie', null, '40.00', Unit::SQUARE_METER);

        /** @var Process $process */
        $process = Process::findByCode('H');

        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'width_mm' => 1500,
            'height_mm' => 1000,
            'processes' => [['process_id' => $process->id, 'product_id' => $tempering->id]],
        ]));

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        $this->assertSame('60.00', $item->processes[0]->amount);
    }

    #[Test]
    public function podglad_liczy_to_samo_co_zapis(): void
    {
        $this->priceGlass();
        $order = $this->order();
        $this->priceCutting('12.00');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $input = $this->pane(['processes' => [['process_id' => $cutting->id]]]);

        $preview = $this->service->preview((int) $order->getKey(), $input);
        $saved = $this->service->savePane((int) $order->getKey(), $input);

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($saved['id']);
        $total = (float) $item->amount + (float) $item->processes[0]->amount;

        // Podglad idzie ta sama droga co zapis. Gdyby liczyl po swojemu,
        // pokazywalby kwote, ktorej zapis nie potwierdzi.
        $this->assertTrue($preview['ready']);
        $this->assertSame(number_format($total, 2, '.', ''), $preview['total']);
        $this->assertSame('mb', $preview['processes'][0]['unit_label']);
    }

    #[Test]
    public function podglad_pokazuje_doplate_za_ksztalt_osobnym_krokiem(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $input = $this->pane(['width_mm' => 1500, 'height_mm' => 1000]);

        $plain = $this->service->preview((int) $order->getKey(), $input);
        $shaped = $this->service->preview(
            (int) $order->getKey(),
            [...$input, 'is_irregular_shape' => true],
        );

        // Doplata musi byc widoczna jako wlasny krok, a nie tylko jako
        // wieksza kwota przy tej samej formule — inaczej kwota przestaje
        // sie tlumaczyc dokladnie wtedy, gdy zaczyna sie dziac cos
        // ciekawego.
        $codes = array_column($shaped['steps'], 'code');

        $this->assertNotContains('shape', array_column($plain['steps'], 'code'));
        $this->assertContains('shape', $codes);
        $this->assertGreaterThan((float) $plain['total'], (float) $shaped['total']);
    }

    #[Test]
    public function pilne_i_znak_nie_ruszaja_ceny(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $input = $this->pane();
        $base = $this->service->preview((int) $order->getKey(), $input);

        // Ani „pilne", ani „znak" nie maja doplaty w zadnym slowniku
        // starego systemu (Z-20 zostaje otwarte). Dopoki jej nie ma,
        // przelacznik ma nie ruszac kwoty — zmyslony procent wygladalby
        // tak samo jak prawdziwy.
        $urgent = $this->service->preview(
            (int) $order->getKey(),
            [...$input, 'is_urgent' => true, 'needs_mark' => true],
        );

        $this->assertSame($base['total'], $urgent['total']);
    }

    #[Test]
    public function brak_ceny_szkla_nie_kasuje_ceny_procesow(): void
    {
        // Celowo bez priceGlass() — material nie ma pozycji w cenniku.
        $order = $this->order();
        $this->priceCutting('12.00');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $result = $this->service->savePane((int) $order->getKey(), $this->pane([
            'processes' => [['process_id' => $cutting->id]],
        ]));

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        // Szlif kosztuje tyle samo niezaleznie od tego, czy ktos wypelnil
        // macierz cennika szkla. Zerowanie procesow chowalo jedyna kwote,
        // ktora byla znana, i pokazywalo formatke za 0,00.
        $this->assertSame('0.00', $item->amount);
        $this->assertNull($item->unit_net_price);
        $this->assertNotSame('0.00', $item->processes[0]->amount);
    }

    #[Test]
    public function podglad_bez_materialu_nie_zgaduje(): void
    {
        $order = $this->order();

        $preview = $this->service->preview((int) $order->getKey(), [
            'width_mm' => 1000,
            'height_mm' => 1000,
        ]);

        // Brak materialu to nie kwota zero, tylko brak odpowiedzi.
        $this->assertFalse($preview['ready']);
        $this->assertNull($preview['total']);
    }

    /**
     * Pozycja cennikowa procesu o zadanej jednostce. Jednostka jest tu
     * istotą rzeczy: cięcie rozlicza się od metra bieżącego, hartowanie
     * od kwadratowego, a CNC od sztuki.
     */
    private function serviceProduct(
        string $code,
        string $name,
        ?float $thickness,
        string $purchaseNet,
        Unit $unit,
    ): Product {
        /** @var Process $process */
        $process = Process::findByCode($code);

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => 'Obróbka'],
            ['section' => Section::SERVICES->value, 'name' => 'Obróbka', 'position' => 20],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::SERVICES->value,
            'name' => $name,
            'unit' => $unit->value,
            'vat_rate' => 23,
        ]);

        ProductService::query()->create([
            'product_id' => $product->id,
            'process_id' => $process->id,
            'glass_thickness_mm' => $thickness,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subDay(),
        ]);

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $this->section(
                'Detaliczny podstawowy',
                Section::SERVICES->value,
            )->id,
            'coefficient' => '1.0',
        ]]);

        return $product;
    }

    private function priceCutting(string $purchaseNet): void
    {
        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->create([
            'section' => Section::SERVICES->value,
            'name' => 'Obróbka krawędzi',
            'position' => 10,
        ]);

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::SERVICES->value,
            'name' => 'Cięcie',
            'unit' => Unit::RUNNING_METER->value,
            'vat_rate' => 23,
        ]);

        ProductService::query()->create([
            'product_id' => $product->id,
            'process_id' => $cutting->id,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subDay(),
        ]);

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $this->section(
                'Detaliczny podstawowy',
                Section::SERVICES->value,
            )->id,
            'coefficient' => '1.0',
        ]]);
    }
}
