<?php

declare(strict_types=1);

namespace Database\Seeders;

use Salvon\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    protected array $seeders = [
        Core\LocationSeeder::class,
        Core\RoleSeeder::class,
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
    ];

    protected function postScript(): void
    {
        //
    }
}
