<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workstations', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedSmallInteger('daily_capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        // Proces jako pozycja slownika, nie kolumna w kodzie. W starym
        // systemie 13 procesow bylo kolumnami siatki produkcji, wiec
        // dodanie procesu wymagalo zmiany layoutu.
        Schema::create('processes', static function (Blueprint $table): void {
            $table->id();
            $table->char('code', 2);
            $table->string('name', 60);
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->string('unit', 8);

            // Rozbicie stalego "czasu trwania" na czas przygotowania
            // i czas jednostkowy. Stary system mial jedna liczbe dni
            // niezalezna od wielkosci partii, wiec 1 formatka i 100 sztuk
            // zajmowaly tyle samo.
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedInteger('setup_minutes')->nullable();
            $table->unsignedInteger('unit_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_days')->default(0);

            // hartowanie jest podzlecane - to czas oczekiwania, nie pracy
            $table->boolean('is_subcontracted')->default(false);
            // proces niosacy parametr: kod RAL, faza, rodzaj folii
            $table->boolean('requires_parameter')->default(false);

            $table->unsignedSmallInteger('default_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique('code');
            $table->index(['is_active', 'default_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
        Schema::dropIfExists('workstations');
    }
};
