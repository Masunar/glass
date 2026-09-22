<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Stawka VAT przy liscie i dane inwestycji przy zleceniu.
 *
 * Dotad stawka byla jedna na zlecenie, brana z typu faktury. To dziala,
 * dopoki zlecenie jest jednorodne — i przestaje, gdy montaz w budynku
 * mieszkalnym idzie na 8 %, a szklo obok na 23 %. W starym systemie
 * obejsciem byl typ faktury „uzgodnione netto 50/50" ze stawka 12 %
 * (`80-slowniki.md` S-06): stawka, ktorej polski VAT nie zna, wpisana
 * recznie jako srednia dwoch prawdziwych.
 *
 * Stawka siedzi na liscie, a nie na pozycji, bo lista jest juz
 * jednostka podzialu zlecenia (pomieszczenie, wariant) i to na niej
 * ludzie i tak rozdzielaja montaz od szkla. `null` znaczy „jak w typie
 * faktury" — nie „zero".
 *
 * Inwestycja siedzi na zleceniu: art. 41 ust. 12b ustawy o VAT wiaze
 * limit powierzchni z obiektem, a nie z pozycja faktury, a ust. 12c
 * kaze przy przekroczeniu limitu rozbic podstawe proporcjonalnie.
 * `investment_type` = null znaczy „nie dotyczy" — zwykla sprzedaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lists', static function (Blueprint $table): void {
            // Bez wartosci domyslnej i nullable: „nie ustawiono" musi
            // dac sie odroznic od „ustawiono zero", bo 0 % to prawdziwa
            // stawka (eksport, odwrotne obciazenie).
            $table->unsignedTinyInteger('vat_rate')->nullable()->after('is_on_hold');
        });

        Schema::table('orders', static function (Blueprint $table): void {
            $table->string('investment_type', 20)->nullable()->after('accounting_note');
            $table->decimal('investment_area_m2', 10, 2)->nullable()->after('investment_type');
        });
    }

    public function down(): void
    {
        Schema::table('order_lists', static function (Blueprint $table): void {
            $table->dropColumn('vat_rate');
        });

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropColumn(['investment_type', 'investment_area_m2']);
        });
    }
};
