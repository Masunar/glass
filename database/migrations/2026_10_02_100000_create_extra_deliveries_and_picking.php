<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Dostawy dodatkowe do zleceń i kompletacja okuć.
 *
 * Uwagi klienta (25.09), punkty 3c i 3d. Decyzje Marcina:
 *  - dostawa dodatkowa (reklamacja, błędne okucie, domówienie) to
 *    **osobny dokument**, zawsze dla konkretnego zlecenia — nie pozycja
 *    zamówienia do dostawcy;
 *  - magazynier odhacza zlecenie jako przygotowane, z tym kto i kiedy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->timestamp('fittings_prepared_at')->nullable();
            $table->foreignId('fittings_prepared_by')->nullable()
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('extra_deliveries', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            // Dostawa dodatkowa bez zlecenia to zwykle zamowienie — po
            // to jest osobny dokument, zeby zawsze wiedziec, dla kogo.
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('reason', 20);
            $table->string('status', 20);
            $table->date('expected_at')->nullable();
            $table->date('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_at']);
        });

        Schema::create('extra_delivery_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extra_delivery_id')->constrained('extra_deliveries')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_delivery_items');
        Schema::dropIfExists('extra_deliveries');

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fittings_prepared_by');
            $table->dropColumn('fittings_prepared_at');
        });
    }
};
