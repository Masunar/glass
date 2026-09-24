<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\PermissionPackage;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Paczka jako wiązanie, nie kopia (U-07).
 *
 * Do tej pory uprawnienia z paczki widział wyłącznie ekran `/access`,
 * bo liczył je `AccessResolver`, a sprawdzanie dostępu szło przez
 * spatie, które o paczkach nie wie. Skutek był niemy w obie strony:
 * rola „Handlowiec" pokazywała pełne pokrycie, a jej użytkownik nie
 * widział ani jednego modułu. **Każda strona była zgodna ze swoim
 * źródłem — tylko źródła były dwa.**
 *
 * Testy pilnują tego, czego nie widać: że uprawnienie z paczki działa
 * **bez ręcznego zapisania roli** i że poprawiona paczka zmienia dostęp
 * **od razu**, bo inaczej wiązanie byłoby kopią z chwili zapisu.
 */
class PackagePermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function uprawnienie_z_paczki_dziala_bez_zapisywania_roli(): void
    {
        $user = $this->userWithPackage(['adm.access', 'users.list']);

        // Rola nie ma ani jednego uprawnienia wlasnego — wszystko
        // pochodzi z paczki.
        $this->assertTrue($user->can('adm.access'));
        $this->assertTrue($user->can('users.list'));
    }

    #[Test]
    public function trasa_chroniona_przepuszcza_uprawnienie_z_paczki(): void
    {
        $user = $this->userWithPackage(['adm.access', 'users.list']);

        // Po prawdziwym HTTP, bo posrednik wyprowadza wymaganie
        // z deklaracji kontrolera — sprawdzenie na samym modelu nie
        // powiedzialoby, czy trasa tez przepuszcza.
        $this->actingAs($user)->getJson('/api/users/board')->assertOk();
    }

    #[Test]
    public function paczka_nie_daje_nic_ponad_swoja_zawartosc(): void
    {
        $user = $this->userWithPackage(['adm.access', 'users.list']);

        $this->assertFalse($user->can('users.delete'));
        $this->assertFalse($user->can('orders.list'));
    }

    #[Test]
    public function zabranie_pozycji_z_paczki_odbiera_dostep_od_razu(): void
    {
        $user = $this->userWithPackage(['adm.access', 'users.list']);

        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()->where('name', 'Paczka testowa')->firstOrFail();
        $package->permissions()->sync($this->ids(['adm.access']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Wiazanie, nie kopia: poprawiona paczka zmienia dostep bez
        // otwierania i zapisywania kazdej roli, ktora ja ma.
        $fresh = $user->fresh() ?? $user;

        $this->assertTrue($fresh->can('adm.access'));
        $this->assertFalse($fresh->can('users.list'));
    }

    #[Test]
    public function zaseedowana_rola_handlowca_widzi_zlecenia(): void
    {
        /** @var Role $role */
        $role = Role::query()->where('name', RoleSeeder::SALES)->firstOrFail();

        $user = $this->user();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $fresh = $user->fresh() ?? $user;

        // To jest ten test, ktorego brakowalo. Zasiew przypina rolom
        // paczki i nie nadaje ani jednego uprawnienia — wiec konto
        // z zaseedowana rola nie widzialo niczego, a ekran ról mowil,
        // ze wszystko jest skonfigurowane.
        $this->assertTrue($fresh->can('zlec.access'));
        $this->assertTrue($fresh->can('orders.list'));
        $this->assertTrue($fresh->can('prod.access'));
        $this->assertTrue($fresh->can('production.list'));

        // Paczka „Produkcja — podglad" nie daje prawa zmiany.
        $this->assertFalse($fresh->can('production.update'));
    }

    /**
     * @param list<string> $permissions
     */
    private function userWithPackage(array $permissions): User
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()->create([
            'name' => 'Paczka testowa',
            'description' => 'Paczka na potrzeby testu wiązania.',
        ]);

        $package->permissions()->sync($this->ids($permissions));

        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola z paczka ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->packages()->sync([$package->getKey()]);

        $user = $this->user();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh() ?? $user;
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    private function ids(array $names): array
    {
        /** @var list<int> */
        return Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();
    }

    private function user(): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Paczka',
            'email' => 'paczka' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);
    }
}
