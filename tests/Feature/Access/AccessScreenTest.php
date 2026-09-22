<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
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
 * Pilnowane są tu rzeczy, które są **spłatą** za wybory z planu,
 * a nie ozdobą ekranu: bilans zapisu, zasięg zmiany paczki, to, że
 * roli nadrzędnej nie da się konfigurować, i że nadanie przy
 * użytkowniku dokłada się do roli, zamiast ją dublować.
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
    // Edytor paczki
    // ---------------------------------------------------------------

    #[Test]
    public function edytor_paczki_podaje_zasieg_przed_zapisem(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);
        $this->role('Rola A')->packages()->attach($package->getKey());
        $this->role('Rola B')->packages()->attach($package->getKey());

        $board = $this->board->package((int) $package->getKey());

        // Zasieg **przed** kliknieciem, nie w bilansie po fakcie —
        // to jedyna operacja dzialajaca na kogos spoza ekranu.
        $this->assertSame(['Rola A', 'Rola B'], $board['package']['roles']);
    }

    #[Test]
    public function edytor_paczki_nie_podpisuje_uprawnien_wlasna_paczka(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);

        $groups = collect($this->board->package((int) $package->getKey())['groups'])
            ->keyBy('key');
        $item = collect($groups['orders']['items'])->firstWhere('name', 'orders.list');

        $this->assertTrue($item['granted']);
        // Wewnatrz wlasnej paczki napis „paczka Sprzedaz" przy kazdym
        // wierszu bylby samym szumem.
        $this->assertNull($item['origin']);
    }

    #[Test]
    public function nowa_paczka_zaczyna_od_pustego_drzewa(): void
    {
        $board = $this->board->package(null);

        $this->assertNull($board['package']);
        $granted = collect($board['groups'])->sum('granted');
        $this->assertSame(0, $granted);
    }

    // ---------------------------------------------------------------
    // Uprawnienia ponad rolę
    // ---------------------------------------------------------------

    #[Test]
    public function uzytkownik_dostaje_uprawnienie_ponad_role(): void
    {
        $user = $this->user('ponad@test.pl', 'Rola A');

        $result = $this->service->saveUser((int) $user->getKey(), [
            'permissions' => ['warehouse.list'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['balance']['granted']);
        $this->assertTrue($user->fresh()?->hasDirectPermission('warehouse.list'));
    }

    #[Test]
    public function to_co_daje_rola_nie_zapisuje_sie_drugi_raz(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');

        $user = $this->user('dubel@test.pl', 'Rola A');

        $result = $this->service->saveUser((int) $user->getKey(), [
            'permissions' => ['orders.list', 'warehouse.list'],
        ]);

        // Zostawione, przezyloby odebranie uprawnienia roli — i tak
        // powstaja konta, ktore po degradacji dalej wszystko widza.
        $this->assertSame(1, $result['balance']['skipped']);
        $this->assertSame(['warehouse.list'], $user->fresh()?->permissions->pluck('name')->all());
    }

    #[Test]
    public function uzytkownikowi_z_rola_nadrzedna_nie_da_sie_nic_nadac(): void
    {
        $user = $this->user('admin@test.pl', RoleSeeder::ADMINISTRATOR);

        $result = $this->service->saveUser((int) $user->getKey(), [
            'permissions' => ['warehouse.list'],
        ]);

        $this->assertArrayHasKey('user', $result['errors']);
    }

    #[Test]
    public function ekran_uzytkownika_oddziela_nadane_wprost_od_roli(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo('orders.list');

        $user = $this->user('mieszane@test.pl', 'Rola A');
        $user->givePermissionTo('warehouse.list');

        $board = $this->board->user((int) $user->getKey());

        // Tylko nadane wprost da sie tu odznaczyc — reszta nalezy do
        // roli i odbiera sie ja przy roli.
        $this->assertSame(['warehouse.list'], $board['direct']);

        $groups = collect($board['groups'])->keyBy('key');
        $fromRole = collect($groups['orders']['items'])->firstWhere('name', 'orders.list');
        $this->assertSame('rola Rola A', $fromRole['origin']);
    }

    #[Test]
    public function zapis_przy_uzytkowniku_zostawia_slad(): void
    {
        $user = $this->user('slad@test.pl', 'Rola A');

        $this->service->saveUser((int) $user->getKey(), [
            'permissions' => ['warehouse.list'],
        ]);

        $this->assertDatabaseHas('audit_entries', [
            'auditable_type' => User::class,
            'auditable_id' => $user->getKey(),
            'event' => 'user_permissions_changed',
        ]);
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function user(string $email, string $roleName): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Jan',
            'last_name' => 'Testowy',
            'email' => $email,
            'password' => 'x',
            'is_active' => true,
        ]);

        $user->roles()->attach(
            Role::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['name' => $roleName, 'guard_name' => 'web'],
            ),
        );

        return $user->fresh() ?? $user;
    }

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
