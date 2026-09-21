<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tempering_batches', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            // Hartownia jako dostawca — ten sam slownik, co przy
            // zamowieniach towaru. Podwykonawca to tez ktos, od kogo
            // kupujemy, tylko uslugę zamiast rzeczy.
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('sent_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->date('returned_at')->nullable();
            // Koszt partii. Nie wchodzi do wyceny zlecenia — H-08 otwarte.
            $table->decimal('net_cost', 12, 2)->nullable();
            $table->string('document', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_at']);
        });

        Schema::create('tempering_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('tempering_batch_id')->nullable()
                ->constrained('tempering_batches')->nullOnDelete();
            $table->string('status', 20);
            // Ilosc, bo z dwudziestu czterech sztuk potluc sie moga trzy.
            $table->decimal('quantity', 12, 3);
            // Pozycja zastepcza wskazuje ta, ktora sie stlukla — inaczej
            // po miesiacu nie da sie powiedziec, dlaczego ta sama
            // formatka jechala do pieca dwa razy.
            $table->foreignId('replaces_id')->nullable()
                ->constrained('tempering_items')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'tempering_batch_id']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tempering_items');
        Schema::dropIfExists('tempering_batches');
    }
};
