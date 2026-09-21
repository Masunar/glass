<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tempering_batches', static function (Blueprint $table): void {
            // Partia to kurs auta. Nullowalne, bo kurs planuje sie czesto
            // zanim wiadomo, ktore auto pojedzie — a lista szyb jest
            // potrzebna wczesniej niz przydzial pojazdu.
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('vehicles')
                ->nullOnDelete();

            // Planowany wyjazd, osobno od `sent_at`, ktory jest faktem.
            // Roznica miedzy nimi to informacja, czy plan sie trzyma.
            $table->date('departure_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tempering_batches', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn('departure_at');
        });
    }
};
