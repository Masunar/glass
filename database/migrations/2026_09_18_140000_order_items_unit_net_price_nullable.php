<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Cena jednostkowa pozycji może być pusta.
 *
 * „Brak ceny nie jest zerem" jest zasadą silnika wyceny od początku
 * (`95-silnik-wyceny.md` §5.1), ale zapis jej nie dotrzymywał: pozycja
 * bez pozycji w cenniku szła do bazy jako 0,00 i od tej chwili nie dało
 * się już odróżnić szkła za darmo od szkła o nieznanej cenie.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->decimal('unit_net_price', 12, 2)->nullable()->default(null)->change();
        });

        // Zera zapisane wczesniej zostaja zerami — nie wiadomo, ktore
        // z nich byly brakiem ceny, a ktore cena wpisana z reki.
    }

    public function down(): void
    {
        DB::table('order_items')->whereNull('unit_net_price')->update(['unit_net_price' => 0]);

        Schema::table('order_items', static function (Blueprint $table): void {
            $table->decimal('unit_net_price', 12, 2)->nullable(false)->default(0)->change();
        });
    }
};
