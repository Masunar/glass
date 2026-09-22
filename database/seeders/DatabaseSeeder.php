<?php

declare(strict_types=1);

namespace Database\Seeders;

use Salvon\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    protected array $seeders = [
        Core\LocationSeeder::class,
        Core\RoleSeeder::class,
        // Po rolach, bo paczki i nadania wisza na rolach.
        Core\PermissionSeeder::class,
        // Po uprawnieniach, bo paczka wiaze istniejace wiersze.
        Core\PermissionPackageSeeder::class,
        Core\StatusSeeder::class,
        Core\ProcessSeeder::class,
        Core\GlassCatalogSeeder::class,
        Core\PriceSectionSeeder::class,
        Core\GlobalParameterSeeder::class,
        Core\DictionarySeeder::class,
        Core\SettingSeeder::class,
        Core\EmailTemplateSeeder::class,
    ];
    protected array $productionSeeders = [];

    protected array $devSeeders = [
        Dev\UserSeeder::class,
        // Przed zleceniami: OrderSeeder czyta z tego cennika zamiast
        // wpisywac ceny procesow z palca.
        Dev\ProcessPriceSeeder::class,
        // Macierz cennika szkla — bez niej material nie ma ceny
        // katalogowej i kazda formatka wychodzi po 0,00.
        Dev\GlassPriceSeeder::class,
        // Okucia razem ze stanami i biblioteka zestawow — przed
        // zleceniami, bo OrderSeeder moze z nich kiedys korzystac.
        Dev\FittingSeeder::class,
        Dev\ContractorSeeder::class,
        Dev\OrderSeeder::class,
    ];

    protected function postScript(): void
    {
        //
    }
}
