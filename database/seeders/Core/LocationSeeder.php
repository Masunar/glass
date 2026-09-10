<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\Location;
use Salvon\Database\Seeder;

/**
 * Dwa punkty firmy, potwierdzone niezależnie w czterech miejscach starego
 * systemu: lokalizacja użytkownika, punkt realizacji zlecenia, typy wpłat
 * (Chopina got. / Stobno got.) i sufiks O1/O2 przy numerze zlecenia.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Stobno',
                'short_name' => 'STO',
                'phone' => '603 666 014',
                'email' => 'biuro@glassexpert.pl',
                'is_production' => true,
                'is_pickup_point' => true,
                'is_default' => true,
                'position' => 10,
            ],
            [
                'name' => 'Chopina',
                'short_name' => 'CHO',
                'phone' => '91 45 42 475',
                'email' => 'biuro@szklojaz.pl',
                'is_production' => false,
                'is_pickup_point' => true,
                'is_default' => false,
                'position' => 20,
            ],
        ];

        foreach ($locations as $location) {
            Location::query()->firstOrCreate(['name' => $location['name']], $location);
        }
    }
}
