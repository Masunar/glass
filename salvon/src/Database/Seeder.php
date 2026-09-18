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

    /**
     * Zegar, na którym sieją się dane deweloperskie.
     *
     * Dane rdzeniowe sieją się przed zamrożeniem, czyli na zegarze
     * rzeczywistym, a deweloperskie po nim — na tym. Jeżeli seeder
     * deweloperski czyta coś, co zapisał rdzeniowy, i obie strony datują
     * po `Carbon::today()`, to czyta z przeszłości rzecz zapisaną
     * w przyszłości i nie znajduje jej. Stąd `referenceDate()`.
     */
    public const DEV_NOW = '2024-01-01 12:00:00';

    /**
     * Data, od której obowiązują dane słownikowe: ceny zakupu,
     * parametry globalne, pozycje cennika.
     *
     * Wcześniejsza od obu zegarów, więc widoczna niezależnie od tego,
     * na którym z nich pyta się o wartość. „Obowiązuje od zawsze" —
     * bo dla danych startowych żadna prawdziwa data początku nie
     * istnieje, a `Carbon::today()->startOfYear()` dawało wynik
     * zależny od dnia, w którym uruchomiono seeder.
     */
    public static function referenceDate(): Carbon
    {
        return Carbon::parse(self::DEV_NOW)->startOfYear();
    }

    public function run(): void
    {
        $this->commonSeeders();

        if (Env::isProduction()) {
            $this->productionSeeders();
            return;
        }

        // Zegar wraca na miejsce takze wtedy, gdy gałąź testowa konczy
        // sie wczesniej. Bez `finally` sianie danych testowych zostawialo
        // zamrozony zegar na caly proces, a testy liczyly czas od 2024
        // roku — objaw dowolnie odlegly od przyczyny.
        Carbon::setTestNow(Carbon::parse(self::DEV_NOW));

        try {
            if (Env::isTest()) {
                $this->testSeeders();

                return;
            }

            $this->devSeeders();
        } finally {
            Carbon::setTestNow();
        }

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
