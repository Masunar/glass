<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `is_superuser` to kolumna dodana migracją do tabeli pakietu Spatie.
 * Sam pakiet o niej nie wie, więc podstawiamy własny model w konfiguracji.
 *
 * To najbardziej doniosłe pole w systemie uprawnień: rola nadrzędna
 * omija sprawdzanie uprawnień w całości. Podstawienie modelu przez
 * konfigurację działa albo nie działa po cichu — stąd te testy.
 */
class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
    }

    #[Test]
    public function konfiguracja_wskazuje_nasz_model(): void
    {
        $this->assertSame(Role::class, config('permission.models.role'));
    }

    #[Test]
    public function relacja_uzytkownika_oddaje_nasz_model(): void
    {
        // Bez podstawienia w konfiguracji $user->roles zwracaloby klase
        // pakietu, ktora o fladze nie wie — i flaga czytana bylaby
        // przez getAttribute w kazdym miejscu z osobna.
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Marcin',
            'email' => 'role@test.pl',
            'password' => 'x',
            'is_active' => true,
        ]);

        $user->roles()->attach(
            Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail(),
        );

        $role = $user->fresh()?->roles->first();

        $this->assertInstanceOf(Role::class, $role);
    }

    #[Test]
    public function flaga_jest_rzutowana_na_boolean(): void
    {
        /** @var Role $administrator */
        $administrator = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();
        /** @var Role $sales */
        $sales = Role::query()->where('name', RoleSeeder::SALES)->firstOrFail();

        // Nie "1" i nie 1 — assertSame lapie rzutowanie, assertTrue nie.
        $this->assertSame(true, $administrator->is_superuser);
        $this->assertSame(false, $sales->is_superuser);
    }

    #[Test]
    public function uzytkownik_bez_roli_nadrzednej_nie_jest_nadrzedny(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Paulina',
            'email' => 'handlowiec@test.pl',
            'password' => 'x',
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::query()->where('name', RoleSeeder::SALES)->firstOrFail());

        $this->assertFalse($user->fresh()?->isSuperUser());
    }

    #[Test]
    public function uzytkownik_z_rola_nadrzedna_jest_nadrzedny(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Marcin',
            'email' => 'admin2@test.pl',
            'password' => 'x',
            'is_active' => true,
        ]);

        // Dwie role, tylko jedna nadrzedna — wystarczy jedna.
        $user->roles()->attach(Role::query()->where('name', RoleSeeder::SALES)->firstOrFail());
        $user->roles()->attach(
            Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail(),
        );

        $this->assertTrue($user->fresh()?->isSuperUser());
    }

    #[Test]
    public function tylko_administrator_jest_rola_nadrzedna(): void
    {
        // Rola nadrzedna omija cala kontrole uprawnien, wiec ich liczba
        // jest decyzja, a nie szczegolem — test ma zaboleć, gdy ktos
        // dopisze druga.
        $superusers = Role::query()->where('is_superuser', true)->pluck('name')->all();

        $this->assertSame([RoleSeeder::ADMINISTRATOR], $superusers);
    }
}
