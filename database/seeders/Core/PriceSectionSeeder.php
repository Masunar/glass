<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Enum\Section;
use App\Models\PriceSection;
use Salvon\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\RoleDiscountLimit;

/**
 * Sekcje cenowe wraz z limitami rabatowymi ról.
 *
 * Zasada warta zachowania: im tańsza sekcja cenowa, tym mniejszy rabat
 * dodatkowy może udzielić handlowiec. Klient na najniższym poziomie ma
 * limit zerowy, bo już ma najniższą cenę.
 *
 * Limity są wierszami rola × sekcja, nie kolumnami — w starym systemie
 * kolumny istniały dla trzech ról, a ról jest osiem.
 */
class PriceSectionSeeder extends Seeder
{
    public function run(): void
    {
        // sekcja, nazwa, pozycja, domyslna, [admin, starszy handlowiec, handlowiec]
        $sections = [
            [Section::GLASS, 'Detaliczny extra', 0, false, [100, 20, 15]],
            [Section::GLASS, 'Detaliczny podstawowy', 1, true, [100, 15, 10]],
            [Section::GLASS, 'Detaliczny stały klient', 2, false, [100, 10, 7]],
            [Section::GLASS, 'Biznesowy strefa 1', 3, false, [100, 10, 7]],
            [Section::GLASS, 'Biznesowy strefa 2', 4, false, [100, 7, 5]],
            [Section::GLASS, 'Biznesowy strefa 3', 5, false, [100, 5, 0]],

            [Section::FITTINGS, 'Detaliczny podstawowy', 10, true, [100, 15, 10]],
            [Section::FITTINGS, 'Biznesowy ceny fabryczne', 11, false, [100, 7, 5]],
            [Section::FITTINGS, 'Biznesowy −28%', 12, false, [100, 0, 0]],

            [Section::SERVICES, 'Detaliczny extra', 20, false, [100, 15, 10]],
            [Section::SERVICES, 'Detaliczny podstawowy', 21, true, [100, 15, 10]],
            [Section::SERVICES, 'Biznesowy', 22, false, [100, 7, 5]],

            [Section::OTHER, 'Detaliczny podstawowy', 40, true, [100, 15, 10]],
        ];

        $roleNames = [RoleSeeder::ADMINISTRATOR, RoleSeeder::SENIOR_SALES, RoleSeeder::SALES];

        foreach ($sections as [$section, $name, $position, $isDefault, $limits]) {
            /** @var PriceSection $priceSection */
            $priceSection = PriceSection::query()->firstOrCreate(
                ['section' => $section->value, 'name' => $name],
                [
                    'section' => $section->value,
                    'name' => $name,
                    'position' => $position,
                    'is_default' => $isDefault,
                ],
            );

            foreach ($roleNames as $index => $roleName) {
                $role = Role::query()->where('name', $roleName)->first();

                if ($role === null) {
                    continue;
                }

                RoleDiscountLimit::query()->firstOrCreate(
                    ['price_section_id' => $priceSection->id, 'role_id' => $role->id],
                    [
                        'price_section_id' => $priceSection->id,
                        'role_id' => $role->id,
                        'max_discount_percent' => $limits[$index],
                    ],
                );
            }
        }
    }
}
