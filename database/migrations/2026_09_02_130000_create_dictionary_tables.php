<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_types', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->unsignedTinyInteger('vat_rate');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        // Kasa jako miejsce lub osoba przyjmujaca wplate. Kanal i waluta
        // sa osobnymi wymiarami na samej wplacie - stary slownik "Typy
        // wplat" mieszal wszystkie trzy w jednej liscie.
        Schema::create('cash_registers', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->char('default_currency', 3)->default('PLN');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('vehicles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('short_name', 10)->nullable();
            // dopuszczalna masa ladunku w kilogramach
            $table->unsignedInteger('payload_kg');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedTinyInteger('crew_slots')->default(3);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        // Parametry wzoru wyceny i tekstow ofertowych. Typowane
        // i wersjonowane: zmiana doplaty za ksztalt z 35 na 40 zmienia
        // ceny wszystkich nowych ofert i musi zostawiac slad.
        Schema::create('global_parameters', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60);
            $table->string('type', 20);
            $table->text('value')->nullable();
            $table->string('description', 200)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_parameters');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('invoice_types');
    }
};
