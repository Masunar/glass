<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use Salvon\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Osiem ról systemowych z dokumentacji modułu Użytkownicy.
 *
 * Role są danymi referencyjnymi, nie danymi deweloperskimi — limity
 * rabatowe sekcji cenowych odwołują się do nich, więc muszą istnieć
 * także na produkcji.
 *
 * Świadomie pominięta rola widoczna w starym systemie jako surowy klucz
 * i18n `USER.TYPES.EDITOR`: nie wiadomo, czym jest ani co ma robić.
 */
class RoleSeeder extends Seeder
{
    public const ADMINISTRATOR = 'Administrator';
    public const SENIOR_SALES = 'Starszy handlowiec';
    public const SALES = 'Handlowiec';

    public function run(): void
    {
        $roles = [
            [self::ADMINISTRATOR, true],
            [self::SENIOR_SALES, false],
            [self::SALES, false],
            ['Grafik', false],
            ['Księgowa', false],
            ['Szef produkcji', false],
            ['Pracownik', false],
        ];

        foreach ($roles as [$name, $isSuperuser]) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web'],
            );

            // Kolumna dodana migracja do tabeli Spatie, a nie polem ich
            // modelu — stad odczyt przez getAttribute.
            if ($isSuperuser && $role->getAttribute('is_superuser') !== true) {
                $role->forceFill(['is_superuser' => true])->save();
            }
        }
    }
}
