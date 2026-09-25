<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Krótka uwaga do zlecenia mieści tysiąc znaków zamiast dwustu.
 *
 * Uwaga klienta (25.09): biuro zapisuje w niej ustalenia z telefonu
 * i dwieście znaków kończyło się w połowie zdania. Pogrubienie
 * zapisujemy gwiazdkami w tym samym polu, więc znaczniki też liczą się
 * do limitu — tysiąc zostawia na nie zapas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->string('short_note', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->string('short_note', 200)->nullable()->change();
        });
    }
};
