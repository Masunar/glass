<?php

declare(strict_types=1);

namespace Salvon\Database;

use Carbon\Carbon;
use Salvon\Service\Env;
use Illuminate\Database\Seeder as LaravelSeeder;

abstract class Seeder extends LaravelSeeder
{
    protected array $seeders = [];

    protected array $devSeeders = [];

    protected array $productionSeeders = [];

    protected array $testSeeders = [];

    public function run(): void
    {
        $this->commonSeeders();

        if (Env::isProduction()) {
            $this->productionSeeders();
            return;
        }

        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));
        if (Env::isTest()) {
            $this->testSeeders();
            return;
        }

        $this->devSeeders();

        Carbon::setTestNow();

        $this->postScript();
    }

    public function commonSeeders(): void
    {
        each($this->seeders, fn(string $seeder) => $this->call($seeder));
    }

    public function devSeeders(): void
    {
        each($this->devSeeders, fn(string $seeder) => $this->call($seeder));
    }

    public function productionSeeders(): void
    {
        each($this->productionSeeders, fn(string $seeder) => $this->call($seeder));
    }

    public function testSeeders(): void
    {
        each($this->testSeeders, fn(string $seeder) => $this->call($seeder));
    }

    protected function postScript(): void
    {
        //
    }
}
