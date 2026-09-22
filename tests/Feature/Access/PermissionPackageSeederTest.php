<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\Role;
use App\Models\PermissionPackage;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\PermissionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Paczki startowe.
 *
 * Zasiew ma dać siedmiu pustym rolom punkt wyjścia, **nie** decydować
 * za administratora. Pilnowane są tu dwie granice: rola raz tknięta
 * zostaje jego, a tam gdzie macierz z dokumentacji mówi więcej, niż
 * model potrafi, nie nadajemy nic.
 */
class PermissionPackageSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paczka_nadaje_uprawnienia_z_rejestru(): void
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()
            ->where('name', 'Zlecenia — obsługa')
            ->firstOrFail();

        $names = $package->permissions->pluck('name')->all();

        $this->assertContains('zlec.access', $names);
        $this->assertContains('orders.create', $names);
        // Cennik tylko do odczytu — obsluga zlecen go czyta, nie ustala.
        $this->assertNotContains('price_list.update', $names);
    }

    #[Test]
    public function handlowiec_ma_z_czego_zaczac(): void
    {
        /** @var Role $role */
        $role = Role::query()->where('name', RoleSeeder::SALES)->firstOrFail();

        $this->assertGreaterThan(0, $role->packages()->count());
    }

    #[Test]
    public function pracownik_zostaje_pusty(): void
    {
        /** @var Role $role */
        $role = Role::query()->where('name', 'Pracownik')->firstOrFail();

        // Macierz daje mu „zlecenia wlasne", a zakresu danych w modelu
        // nie ma. `orders.list` otworzyloby wszystkie zlecenia z cenami
        // — czyli wiecej, niz mowi dokument. Brak zakresu nie jest zgoda.
        $this->assertSame(0, $role->packages()->count());
        $this->assertSame(0, $role->permissions()->count());
    }

    #[Test]
    public function rola_juz_skonfigurowana_nie_jest_ruszana(): void
    {
        /** @var Role $role */
        $role = Role::query()->where('name', RoleSeeder::SALES)->firstOrFail();
        $role->packages()->detach();
        $role->givePermissionTo('warehouse.list');

        (new PermissionPackageSeeder())->run();

        // Zasiew uzupelnia puste miejsce, nie poprawia cudzej pracy —
        // inaczej kazde wdrozenie cofaloby ustawienia administratora.
        $this->assertSame(0, $role->packages()->count());
    }

    #[Test]
    public function powtorny_zasiew_nie_nadpisuje_zawartosci_paczki(): void
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()
            ->where('name', 'Magazyn — podgląd')
            ->firstOrFail();

        $package->permissions()->detach();

        (new PermissionPackageSeeder())->run();

        $this->assertSame(0, $package->permissions()->count());
    }
}
