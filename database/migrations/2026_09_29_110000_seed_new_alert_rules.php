<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\Core\AlertRuleSeeder;

/**
 * Dopisuje nowe reguły do działającej bazy.
 *
 * Zasiew reguł jest zachowawczy — reguła o istniejącym kodzie zostaje
 * nietknięta — więc uruchomienie go tutaj dokłada tylko brakujące:
 * „ponad limitem", „za długo w statusie", „brak wyceny". Bez tego nowe
 * typy byłyby w katalogu, a na `/alerts` nie byłoby ani jednej reguły.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AlertRuleSeeder())->run();
    }

    public function down(): void
    {
        // Reguly moga juz miec wystapienia i odhaczenia — nie kasujemy
        // ich przy cofaniu migracji. Wylacza sie je na `/alerts`.
    }
};
