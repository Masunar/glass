<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Silnik regul alertow jako wspolny komponent: warunek -> etykieta ->
        // kolor -> modul, edytowalny z panelu admina. Reguly sa danymi,
        // nie kodem - dodanie alertu nie wymaga wdrozenia.
        Schema::create('alert_rules', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('module', 40);
            $table->string('category', 30);

            $table->json('condition');

            $table->string('label', 120);
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['module', 'is_active']);
        });

        Schema::create('alert_occurrences', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();

            $table->string('alertable_type', 120);
            $table->unsignedBigInteger('alertable_id');

            // wartosc, ktora wywolala alert (np. liczba dni po terminie)
            $table->string('value', 60)->nullable();

            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['alertable_type', 'alertable_id'], 'alert_occurrences_alertable_index');
            $table->index(['alert_rule_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_occurrences');
        Schema::dropIfExists('alert_rules');
    }
};
