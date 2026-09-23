<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\StockLevel;
use App\Models\ProductGroup;
use App\Models\TemperingBatch;
use App\Enum\TemperingBatchStatus;
use App\Alerts\ConditionCatalog;
use App\Enum\AlertConditionType;
use App\Services\DashboardService;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertEngine;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Alerty poza zleceniami (A-02).
 *
 * Do #38 silnik umiał alarmować wyłącznie o zleceniach, więc braki
 * magazynowe i szyby stojące w piecu miały własne ekrany i **nie liczyły
 * się do żadnego alertu**. Testowane jest tu to, co z tego rozszerzenia
 * wynika: że każda reguła umie nazwać swoją rzecz i że pasmo na pulpicie
 * przycina się modułem reguły, a nie jedną wspólną bramką.
 */
class AlertSubjectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function kazdy_warunek_umie_nazwac_swoja_rzecz(): void
    {
        foreach ((new ConditionCatalog())->all() as $key => $condition) {
            // Podpis skladany przy warunku, bo tam stoi zapytanie o te
            // tabele. Bez niego pasmo alertow pokazaloby liczbe bez
            // zadnego „czego" — a liczba bez rzeczy kaze szukac recznie.
            $this->assertSame([], $condition->subjects([]), $key);
            $this->assertNotSame('', $condition->resource(), $key);
        }
    }

    #[Test]
    public function brak_progu_nie_jest_progiem_zerowym(): void
    {
        $product = $this->product('Zawias bez progu');
        StockLevel::query()->create([
            'product_id' => $product->getKey(),
            'quantity' => 0,
            'min_quantity' => 0,
            'max_quantity' => 0,
        ]);

        $condition = (new ConditionCatalog())->find(AlertConditionType::STOCK_BELOW_MINIMUM->value);
        $this->assertNotNull($condition);

        // Pozycja bez ustawionego minimum nie alarmuje nigdy — inaczej
        // caly katalog zapalilby sie pierwszego dnia.
        $this->assertSame([], $condition->find(Carbon::today(), []));
    }

    #[Test]
    public function brak_na_stanie_zapala_alert_z_brakujaca_iloscia(): void
    {
        $product = $this->product('Zawias z progiem');
        StockLevel::query()->create([
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'min_quantity' => 5,
            'max_quantity' => 12,
        ]);

        $condition = (new ConditionCatalog())->find(AlertConditionType::STOCK_BELOW_MINIMUM->value);
        $this->assertNotNull($condition);

        $found = $condition->find(Carbon::today(), []);

        // Brakujaca ilosc, nie stan: rosnie, gdy robi sie gorzej, wiec
        // odhaczenie samo wraca przy poglebieniu braku.
        $this->assertSame('3', $found[(int) $product->getKey()] ?? null);
        $this->assertSame(
            'Zawias z progiem',
            $condition->subjects([(int) $product->getKey()])[(int) $product->getKey()]['label'] ?? null,
        );
    }

    #[Test]
    public function partia_bez_umowionego_powrotu_nie_jest_spozniona(): void
    {
        $this->batch(1, null);
        $late = $this->batch(2, '2026-03-01');

        $condition = (new ConditionCatalog())->find(AlertConditionType::TEMPERING_BATCH_LATE->value);
        $this->assertNotNull($condition);

        $found = $condition->find(Carbon::parse('2026-03-10'), ['days' => 1]);

        // Partia bez `expected_at` nie jest opozniona — jest partia,
        // ktorej nikt nie umowil. Zgadywanie terminu z dnia wysylki
        // byloby wymyslaniem daty nieuzgodnionej z hartownia.
        $this->assertSame(['9'], array_values($found));
        $this->assertSame([(int) $late->getKey()], array_keys($found));
    }

    #[Test]
    public function pasmo_na_pulpicie_przycina_modul_reguly_a_nie_zlecen(): void
    {
        $product = $this->product('Zawias magazynowy');
        StockLevel::query()->create([
            'product_id' => $product->getKey(),
            'quantity' => 0,
            'min_quantity' => 4,
            'max_quantity' => 10,
        ]);

        $board = new DashboardService(new AlertBoard(new AlertEngine()));

        // Magazynier nie widzi zlecen, a brak magazynowy widziec musi.
        // Wspolna bramka „czy widzi zlecenia" byla za waska.
        $codes = array_column($board->board($this->userWith(['mag.access', 'warehouse.list']))['alerts'], 'code');
        $this->assertContains('stock_below_minimum', $codes);

        // I odwrotnie: handlowiec nie ma ogladac pieca ani magazynu.
        $sales = array_column($board->board($this->userWith(['zlec.access', 'orders.list']))['alerts'], 'code');
        $this->assertNotContains('stock_below_minimum', $sales);
    }

    #[Test]
    public function sam_dostep_do_modulu_nie_otwiera_pasma(): void
    {
        $product = $this->product('Zawias bez listy');
        StockLevel::query()->create([
            'product_id' => $product->getKey(),
            'quantity' => 0,
            'min_quantity' => 4,
            'max_quantity' => 10,
        ]);

        $board = new DashboardService(new AlertBoard(new AlertEngine()));

        // Dwa poziomy (U-04): samo `mag.access` bez `warehouse.list`
        // oddaloby liczbe brakow komus, kto nie widzi ani jednej
        // pozycji magazynu.
        $codes = array_column($board->board($this->userWith(['mag.access']))['alerts'], 'code');

        $this->assertNotContains('stock_below_minimum', $codes);
    }

    private function product(string $name): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['name' => 'Alerty magazynowe'],
            ['section' => Section::FITTINGS->value, 'position' => 900, 'is_active' => true],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->getKey(),
            'section' => Section::FITTINGS->value,
            'code' => 'ALR' . random_int(1000, 9999),
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'is_active' => true,
        ]);
    }

    private function batch(int $number, ?string $expected): TemperingBatch
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->firstOrCreate(
            ['name' => 'Hartownia testowa'],
            ['is_active' => true],
        );

        /** @var TemperingBatch */
        return TemperingBatch::query()->create([
            'number' => $number,
            'supplier_id' => $supplier->getKey(),
            'status' => TemperingBatchStatus::SENT->value,
            'sent_at' => '2026-02-20',
            'expected_at' => $expected,
        ]);
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola alertów ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Alerty',
            'email' => 'subj' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh() ?? $user;
    }
}
