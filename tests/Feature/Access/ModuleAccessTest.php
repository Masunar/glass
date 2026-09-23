<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dostęp do modułu jako poziom nad dostępem do zasobu (U-04).
 *
 * Ten test pilnuje rzeczy, która bez niego byłaby **kosmetyką**:
 * listwa chowa moduł, ale `orders.list` bez `zlec.access` nie może
 * otwierać API wklejonym adresem. Poziom, który da się ominąć, nie
 * jest poziomem.
 *
 * Wymaganie nie jest nigdzie wpisane przy trasie — pośrednik wyprowadza
 * je z uprawnień, które kontroler już deklaruje. Dlatego test chodzi po
 * prawdziwym HTTP, a nie po klasie: sprawdza wyprowadzenie, nie
 * deklarację.
 */
class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bez_dostepu_do_modulu_api_odmawia(): void
    {
        $user = $this->userWith(['users.list']);

        $response = $this->actingAs($user)->getJson('/api/users/board');

        $response->assertForbidden();
        // Nazwa uprawnienia wraca do frontu, zeby ekran mogl
        // powiedziec, czego brakuje, a nie „cos poszlo nie tak".
        $response->assertJsonPath('data.permission', 'adm.access');
    }

    #[Test]
    public function z_dostepem_do_modulu_api_przepuszcza(): void
    {
        $user = $this->userWith(['users.list', 'adm.access']);

        $this->actingAs($user)->getJson('/api/users/board')->assertOk();
    }

    #[Test]
    public function sam_dostep_do_modulu_nie_otwiera_zasobu(): void
    {
        $user = $this->userWith(['adm.access']);

        // Dwa poziomy znacza dwa warunki. Gdyby `adm.access`
        // wystarczalo, uprawnienia zasobow stalyby sie ozdoba.
        $this->actingAs($user)->getJson('/api/users/board')->assertForbidden();
    }

    #[Test]
    public function rola_nadrzedna_przechodzi_bez_nadan(): void
    {
        /** @var Role $admin */
        $admin = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $user = $this->user();
        $user->assignRole($admin);

        $this->actingAs($user)->getJson('/api/users/board')->assertOk();
    }

    #[Test]
    public function uprawnienie_nadane_ponad_role_tez_otwiera_modul(): void
    {
        $user = $this->user();
        $user->givePermissionTo('adm.access');
        $user->givePermissionTo('users.list');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Nadanie wprost jest pelnoprawnym zrodlem (U-05) — inaczej
        // panel „Dostep" przy uzytkowniku nic by nie dawal.
        $this->actingAs($user)->getJson('/api/users/board')->assertOk();
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola testowa ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        $user = $this->user();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function user(): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Dostęp',
            'email' => 'modul' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);
    }
}
