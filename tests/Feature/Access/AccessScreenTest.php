<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\PermissionPackage;
use App\Services\Access\AccessBoard;
use App\Services\Access\AccessService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ekran konfiguracji roli i paczek.
 *
 * Pilnowane są tu trzy rzeczy, które są **spłatą** za wybory z planu,
 * a nie ozdobą ekranu: bilans zapisu, zasięg zmiany paczki i to, że
 * roli nadrzędnej nie da się konfigurować.
 */
class AccessScreenTest extends TestCase
{
    use RefreshDatabase;

    private AccessBoard $board;
    private AccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->board = new AccessBoard();
        $this->service = new AccessService();
    }

    // ---------------------------------------------------------------
    // Bilans zamiast „zapisano"
    // ---------------------------------------------------------------

    #[Test]
    public function zapis_wraca_z_bilansem(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');

        $result = $this->service->saveRole((int) $role->getKey(), [
            'permissions' => ['orders.list', 'orders.create', 'zlec.access'],
            'packages' => [],
        ]);

        $this->assertSame([], $result['errors']);
        // „Zapisano" nie mowi nic o operacji, po ktorej chce sie
        // wiedziec dokladnie, co sie zmienilo.
        $this->assertSame(
            ['granted' => 3, 'added' => 2, 'removed' => 0],
            $result['balance'],
        );
    }

    #[Test]
    public function bilans_liczy_takze_odebrane(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');
        $role->givePermissionTo('orders.create');

        $result = $this->service->saveRole((int) $role->getKey(), [
            'permissions' => ['orders.list'],
            'packages' => [],
        ]);

        $this->assertSame(1, $result['balance']['removed']);
    }

    #[Test]
    public function zapis_zostawia_slad_z_nazwami_uprawnien(): void
    {
        $role = $this->role('Rola A');

        $this->service->saveRole((int) $role->getKey(), [
            'permissions' => ['warehouse.list'],
            'packages' => [],
        ]);

        // Sama liczba nie wystarczy: po pol roku pytanie brzmi
        // „kto dal mu magazyn".
        $this->assertDatabaseHas('audit_entries', [
            'auditable_type' => Role::class,
            'auditable_id' => $role->getKey(),
            'event' => 'role_permissions_changed',
        ]);
    }

    #[Test]
    public function nieznane_uprawnienie_nie_przechodzi(): void
    {
        $role = $this->role('Rola A');

        $result = $this->service->saveRole((int) $role->getKey(), [
            'permissions' => ['sierota.list'],
            'packages' => [],
        ]);

        // Nadanie nazwy spoza rejestru byloby zapisem bez skutku.
        $this->assertArrayHasKey('permissions', $result['errors']);
    }

    #[Test]
    public function roli_nadrzednej_nie_da_sie_konfigurowac(): void
    {
        /** @var Role $admin */
        $admin = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $result = $this->service->saveRole((int) $admin->getKey(), [
            'permissions' => [],
            'packages' => [],
        ]);

        // Omija sprawdzanie przez `Gate::before`, wiec odznaczanie
        // bylo by teatrem: ekran pokazywalby brak, a czlowiek dalej
        // wchodzilby wszedzie.
        $this->assertArrayHasKey('role', $result['errors']);
    }

    // ---------------------------------------------------------------
    // Zasięg zmiany paczki
    // ---------------------------------------------------------------

    #[Test]
    public function zapis_paczki_podaje_ile_rol_dotknie(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);
        $this->role('Rola A')->packages()->attach($package->getKey());
        $this->role('Rola B')->packages()->attach($package->getKey());

        $result = $this->service->savePackage((int) $package->getKey(), [
            'name' => 'Sprzedaż',
            'description' => null,
            'permissions' => ['orders.list', 'offers.list'],
        ]);

        // Paczka jest wiazaniem — ta jedna operacja zmienila dwie role,
        // ktore jej nie widzialy.
        $this->assertSame(2, $result['balance']['roles']);
        $this->assertSame(1, $result['balance']['added']);
    }

    #[Test]
    public function uzywanej_paczki_nie_da_sie_skasowac(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);
        $this->role('Rola A')->packages()->attach($package->getKey());

        $result = $this->service->deletePackage((int) $package->getKey());

        // Skasowanie odebraloby uprawnienia komus, kto o tym nie wie.
        $this->assertArrayHasKey('package', $result['errors']);
        $this->assertDatabaseHas('permission_packages', ['id' => $package->getKey()]);
    }

    #[Test]
    public function nieuzywana_paczka_znika(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);

        $result = $this->service->deletePackage((int) $package->getKey());

        $this->assertSame([], $result['errors']);
        $this->assertDatabaseMissing('permission_packages', ['id' => $package->getKey()]);
    }

    // ---------------------------------------------------------------
    // Ekran
    // ---------------------------------------------------------------

    #[Test]
    public function ekran_liczy_pokrycie_stron_w_module(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');

        $modules = collect($this->board->role((int) $role->getKey())['modules'])
            ->keyBy('key');

        // W module zlecen strony na `orders.list` sa pokryte, na
        // `offers.list` i `contractors.list` — nie.
        $this->assertGreaterThan(0, $modules['zlec']['pages_covered']);
        $this->assertLessThan($modules['zlec']['pages'], $modules['zlec']['pages_covered']);
    }

    #[Test]
    public function ekran_podaje_pochodzenie_uprawnienia(): void
    {
        $role = $this->role('Rola A');
        $role->packages()->attach($this->package('Sprzedaż', ['orders.list'])->getKey());

        $groups = collect($this->board->role((int) $role->getKey())['groups'])->keyBy('key');
        $item = collect($groups['orders']['items'])->firstWhere('name', 'orders.list');

        $this->assertTrue($item['granted']);
        $this->assertSame('paczka Sprzedaż', $item['origin']);
    }

    #[Test]
    public function lista_rol_pokazuje_liczbe_problemow(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');

        $row = collect($this->board->roles()['roles'])->firstWhere('name', 'Rola A');

        // Uprawnienie w module bez dostepu do modulu — jeden problem.
        $this->assertSame(1, $row['issues']);
        // I od razu wiadomo, czego dotyczy: „2 problemy" bez nazwy kaze
        // wejsc i sprawdzic, a to jest praca, ktorej da sie uniknac.
        $this->assertSame(['Zlecenia'], $row['issue_labels']);
    }

    #[Test]
    public function rola_nadrzedna_nie_ma_problemow(): void
    {
        $row = collect($this->board->roles()['roles'])
            ->firstWhere('name', RoleSeeder::ADMINISTRATOR);

        $this->assertTrue($row['is_superuser']);
        $this->assertSame(0, $row['issues']);
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function role(string $name): Role
    {
        /** @var Role */
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }

    /** @param list<string> $names */
    private function package(string $name, array $names): PermissionPackage
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()->create(['name' => $name]);

        foreach ($names as $permission) {
            $package->permissions()->attach(
                Permission::query()->where('name', $permission)->firstOrFail()->getKey(),
            );
        }

        return $package;
    }
}
