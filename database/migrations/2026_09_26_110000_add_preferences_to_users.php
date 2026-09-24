<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Ustawienia ekranu zapamietane przy koncie, a nie
            // w przegladarce: ta sama osoba siada raz do biurka, raz do
            // laptopa na hali. Klucze ogranicza `UserPreferences` —
            // kolumna nie jest workiem na dowolne dane.
            $table->json('preferences')->nullable()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('preferences');
        });
    }
};
