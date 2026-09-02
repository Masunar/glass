<?php

declare(strict_types=1);

namespace Database\Seeders;

use Salvon\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    protected array $seeders = [
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
