<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Wykonanie etapu mieszka osobno od marszruty wycenowej.
        // `order_item_processes` sa kasowane i tworzone od nowa przy
        // kazdym zapisie formatki, bo cena jest snapshotem - gdyby stan
        // wykonania siedzial tam, poprawka wymiaru kasowalaby historie
        // pracy hali.
        Schema::create('production_tasks', static function (Blueprint $table): void {
            $table->id();
            // Zlecenie trzymane wprost, nie przez pozycje: warunek
            // przejscia i kolejka stanowiska pytaja o nie przy kazdym
            // wierszu.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->restrictOnDelete();

            // Stanowisko i parametr sa odbitka z chwili utworzenia
            // zadania. Slownik procesow moze sie jutro zmienic, a karta,
            // ktora operator ma przed soba, nie ma sie zmieniac pod reka.
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->string('parameter', 60)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Czas mierzony od teraz, zeby slownik procesow mial kiedys
            // czym wypelnic setup_minutes i unit_minutes. To zegar na
            // scianie, nie czas pracy: obejmuje przerwe i noc, wiec do
            // planowania wolno go uzyc dopiero po odfiltrowaniu.
            $table->unsignedInteger('minutes_spent')->nullable();

            $table->string('issue_type', 20)->nullable();
            $table->string('note', 300)->nullable();

            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Jeden etap na pozycje wystepuje raz. Powtorne wejscie na
            // produkcje ma odnalezc to samo zadanie, nie zalozyc drugie.
            $table->unique(['order_item_id', 'process_id']);
            $table->index(['order_id', 'status']);
            $table->index(['workstation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_tasks');
    }
};
