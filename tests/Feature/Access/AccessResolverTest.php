<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\PermissionPackage;
use App\Support\AccessRegistry;
use App\Services\Access\AccessAudit;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use App\Services\Access\AccessResolver;
use Database\Seeders\Core\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Uprawnienia skuteczne, pochodzenie i spójność.
 *
 * Trzy wybory z planu dają więcej władzy i każdy psuje coś innego.
 * Te testy pilnują spłaty:
 *
 * - **paczka jest wiązaniem** — zmiana paczki musi zmienić role;
 * - **nadanie ponad rolę** — musi być widać, że przyszło wprost;
 * - **`*.access` osobno** — rola z uprawnieniami w module, ale bez
 *   dostępu do modułu, ma zostać nazwana, a nie po cichu naprawiona.
 */
class AccessResolverTest extends TestCase
{
    use RefreshDatabase;

    private AccessResolver $resolver;
    private AccessAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AccessResolver();
        $this->audit = new AccessAudit();
    }

    // ---------------------------------------------------------------
    // Paczka jako wiązanie
    // ---------------------------------------------------------------

    #[Test]
    public function rola_dostaje_uprawnienia_z_paczki(): void
    {
        $role = $this->role('Handlowiec testowy');
        $package = $this->package('Sprzedaż', ['orders.list', 'offers.create']);

        $role->packages()->attach($package->getKey());

        $granted = array_keys($this->resolver->forRole($role->refresh()));

        $this->assertContains('orders.list', $granted);
        $this->assertContains('offers.create', $granted);
    }

    #[Test]
    public function zmiana_paczki_zmienia_role_ktore_ja_maja(): void
    {
        $first = $this->role('Rola A');
        $second = $this->role('Rola B');
        $package = $this->package('Sprzedaż', ['orders.list']);

        $first->packages()->attach($package->getKey());
        $second->packages()->attach($package->getKey());

        // Dolozenie uprawnienia do paczki dziala natychmiast w obu
        // rolach — to jest cala roznica miedzy wiazaniem a szablonem
        // i dokladnie dlatego ekran paczki musi pokazywac zasieg.
        $package->permissions()->attach($this->permission('offers.create')->getKey());

        $this->assertContains('offers.create', array_keys($this->resolver->forRole($first->refresh())));
        $this->assertContains('offers.create', array_keys($this->resolver->forRole($second->refresh())));
    }

    #[Test]
    public function paczka_wie_ktore_role_dotknie_jej_zmiana(): void
    {
        $package = $this->package('Sprzedaż', ['orders.list']);
        $this->role('Rola A')->packages()->attach($package->getKey());
        $this->role('Rola B')->packages()->attach($package->getKey());

        $this->assertCount(2, $package->refresh()->roles);
    }

    #[Test]
    public function uprawnienie_z_paczki_niesie_jej_nazwe(): void
    {
        $role = $this->role('Rola A');
        $role->packages()->attach($this->package('Sprzedaż', ['orders.list'])->getKey());

        $origins = $this->resolver->forRole($role->refresh())['orders.list'];

        $this->assertSame(AccessResolver::FROM_PACKAGE, $origins[0]['from']);
        $this->assertSame('Sprzedaż', $origins[0]['name']);
        $this->assertSame('paczka Sprzedaż', $this->resolver->describe($origins));
    }

    // ---------------------------------------------------------------
    // Nadanie ponad rolę
    // ---------------------------------------------------------------

    #[Test]
    public function uprawnienie_nadane_wprost_doklada_sie_do_roli(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo($this->permission('orders.list'));

        $user = $this->user();
        $user->assignRole($role);
        $user->givePermissionTo($this->permission('warehouse.list'));

        $granted = $this->resolver->namesForUser($user->refresh());

        $this->assertContains('orders.list', $granted);
        $this->assertContains('warehouse.list', $granted);
    }

    #[Test]
    public function widac_ze_uprawnienie_przyszlo_wprost_a_nie_z_roli(): void
    {
        $user = $this->user();
        $user->assignRole($this->role('Rola A'));
        $user->givePermissionTo($this->permission('warehouse.list'));

        $origins = $this->resolver->forUser($user->refresh())['warehouse.list'];

        // Bez tego po roku nikt nie wie, kto co ma i dlaczego.
        $this->assertSame('nadane wprost', $this->resolver->describe($origins));
    }

    #[Test]
    public function to_samo_uprawnienie_z_dwoch_zrodel_pokazuje_oba(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo($this->permission('orders.list'));
        $role->packages()->attach($this->package('Sprzedaż', ['orders.list'])->getKey());

        $described = $this->resolver->describe($this->resolver->forRole($role->refresh())['orders.list']);

        $this->assertStringContainsString('rola Rola A', $described);
        $this->assertStringContainsString('paczka Sprzedaż', $described);
    }

    #[Test]
    public function rola_nadrzedna_ma_wszystko_i_tak_jest_opisana(): void
    {
        /** @var Role $admin */
        $admin = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $granted = $this->resolver->forRole($admin);

        // Wypisywanie jej uprawnien po jednym byloby fikcja: omija
        // sprawdzanie w calosci, wiec ma takze to, co powstanie jutro.
        $this->assertCount(count(AccessRegistry::names()), $granted);
        $this->assertSame(
            AccessResolver::FROM_SUPERUSER,
            $granted['orders.list'][0]['from'],
        );
    }

    // ---------------------------------------------------------------
    // Spójność
    // ---------------------------------------------------------------

    #[Test]
    public function rola_bez_dostepu_do_modulu_dostaje_ostrzezenie(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo($this->permission('orders.list'));

        $issues = $this->audit->forRole($role->refresh());

        $this->assertCount(1, $issues);
        $this->assertSame(AccessAudit::MODULE_WITHOUT_ACCESS, $issues[0]['kind']);
        $this->assertSame('zlec', $issues[0]['module']);
    }

    #[Test]
    public function z_dostepem_do_modulu_ostrzezenia_nie_ma(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo($this->permission('orders.list'));
        $role->givePermissionTo($this->permission('zlec.access'));

        $this->assertSame([], $this->audit->forRole($role->refresh()));
    }

    #[Test]
    public function audyt_nie_naprawia_niczego_sam(): void
    {
        $role = $this->role('Rola A');
        $role->givePermissionTo($this->permission('orders.list'));

        $issues = $this->audit->forRole($role->refresh());

        // Automatyczne dosypanie `zlec.access` cicho rozszerzyloby
        // dostep, o ktory nikt nie prosil. Audyt nazywa problem
        // i na tym konczy.
        $this->assertNotSame([], $issues);
        $this->assertNotContains('zlec.access', array_keys($this->resolver->forRole($role->refresh())));
    }

    #[Test]
    public function zaplanowane_uprawnienia_sa_wypisane_a_nie_ukryte(): void
    {
        $kinds = array_column($this->audit->system(), 'kind');

        $this->assertContains(AccessAudit::PERMISSION_PLANNED, $kinds);
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function role(string $name): Role
    {
        /** @var Role */
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }

    private function permission(string $name): Permission
    {
        /** @var Permission */
        return Permission::query()->where('name', $name)->firstOrFail();
    }

    /** @param list<string> $names */
    private function package(string $name, array $names): PermissionPackage
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()->create(['name' => $name]);

        foreach ($names as $permission) {
            $package->permissions()->attach($this->permission($permission)->getKey());
        }

        return $package;
    }

    private function user(): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Uprawnienia',
            'email' => 'upr' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);
    }
}
