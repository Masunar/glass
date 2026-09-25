<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Termin klienta wyliczany z pozycji i słownik dni wolnych.
 *
 * Uwaga klienta (25.09): system ma sam podać datę, którą da się ręcznie
 * zmienić. Decyzje Marcina: dziś + dni z pozycji w dniach roboczych,
 * bez świąt ustawowych i dni wolnych zakładu; przeliczany do przekazania
 * na produkcję, dopóki nikt go nie zmieni ręcznie.
 *
 * `deadline_days` pamięta, z ilu dni policzono datę — przeliczenie
 * rusza datę tylko wtedy, gdy ta liczba się zmieni. Bez tego poprawka
 * opisu pozycji tydzień później odsuwałaby termin o tydzień.
 *
 * Istniejące terminy są traktowane jak ręczne (decyzja Marcina):
 * ktoś je wpisał, często z oferty, i system nie ma prawa ich ruszyć.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->boolean('deadline_manual')->default(false)->after('client_deadline');
            $table->unsignedSmallInteger('deadline_days')->nullable()->after('deadline_manual');
        });

        DB::table('orders')->whereNotNull('client_deadline')->update(['deadline_manual' => true]);

        Schema::create('days_off', static function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('days_off');

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropColumn(['deadline_manual', 'deadline_days']);
        });
    }
};
