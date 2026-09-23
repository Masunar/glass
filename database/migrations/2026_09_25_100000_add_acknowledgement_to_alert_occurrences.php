<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alert_occurrences', static function (Blueprint $table): void {
            // Odhaczenie to nie zamkniecie. `resolved_at` znaczy „warunek
            // przestal byc spelniony", a odhaczenie — „wiem o tym".
            // Zlecenie po terminie po odhaczeniu dalej jest po terminie.
            $table->timestamp('acknowledged_at')->nullable()->after('triggered_at');
            $table->foreignId('acknowledged_by')
                ->nullable()
                ->after('acknowledged_at')
                ->constrained('users')
                ->nullOnDelete();

            // Wartosc z chwili odhaczenia. Bez niej nie ma z czym porownac
            // „zrobilo sie gorzej", a alert odhaczony przy jednym dniu
            // spoznienia milczalby przy trzydziestu.
            $table->string('acknowledged_value', 60)->nullable()->after('acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('alert_occurrences', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn(['acknowledged_at', 'acknowledged_value']);
        });
    }
};
