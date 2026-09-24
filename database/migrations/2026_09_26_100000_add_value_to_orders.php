<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            // Wartosc zlecenia zapamietana, nie policzona od nowa.
            // Liczy ja dalej wylacznie `OrderValue` — baza przechowuje
            // wynik, zeby saldo kontrahenta bylo suma, a nie wczytaniem
            // wszystkich jego zlecen z pozycjami. Symulacja na 10 000
            // zlecen: siedem sekund na kazde otwarcie listy.
            $table->decimal('value_net', 12, 2)->nullable()->after('owner_id');

            // `null` znaczy „brutto nieznane" (brak stawki VAT), nie zero.
            $table->decimal('value_gross', 12, 2)->nullable()->after('value_net');

            // Nieaktualna wartosc nie jest zerem ani stara kwota — jest
            // do przeliczenia przy najblizszym odczycie. Nowe i istniejace
            // zlecenia startuja jako nieaktualne: pierwsze przeliczenie
            // robi `glass:order-values` albo pierwszy odczyt salda.
            $table->boolean('value_stale')->default(true)->after('value_gross');

            $table->index(['contractor_id', 'value_stale']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex(['contractor_id', 'value_stale']);
            $table->dropColumn(['value_net', 'value_gross', 'value_stale']);
        });
    }
};
