<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Rejestr wplat, nie lista do kasowania. Stary system pozwalal
        // wplate dodac i nie pokazywal jej pozniej nigdzie — nie bylo ani
        // listy, ani korekty. Tutaj korekta jest osobnym wierszem
        // wskazujacym na wplate, ktora odwraca: historia zostaje w calosci.
        Schema::create('payments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();

            // Kwota w walucie wplaty i ta sama kwota po przeliczeniu.
            // Przeliczona jest zapisana, nie liczona przy odczycie: kurs
            // z dnia wplaty nie moze sie zmienic, gdy ktos jutro poprawi
            // tabele kursow.
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 12, 2);

            $table->date('paid_on');
            $table->string('note', 200)->nullable();

            // Korekta wskazuje na wplate, ktora odwraca. Wiersz odwracany
            // zostaje nietkniety — to jest sens storna.
            $table->foreignId('reversal_of_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'paid_on']);
            $table->unique('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
