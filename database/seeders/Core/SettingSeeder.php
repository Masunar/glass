<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\Setting;
use Salvon\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            //
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
