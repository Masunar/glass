<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_at']);
        });

        Schema::create('purchase_order_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            // Cena z zamowienia, nie z cennika: dostawca potwierdza wlasna,
            // a przyjecie moze przyjsc jeszcze z inna. Nullowalna, bo
            // szkic zamowienia powstaje czesto przed potwierdzeniem ceny.
            $table->decimal('unit_net_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
        });

        // Przyjecie jako dokument, zeby dalo sie powiedziec „ta sztuka
        // przyszla tym samochodem, po tej cenie". Bez tego czesciowe
        // dostawy sa tylko rosnaca liczba w kolumnie.
        Schema::create('purchase_receipts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('received_at');
            $table->string('document', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            // Cena z faktury dostawcy. Z niej — i tylko z niej — bierze
            // sie nowa cena zakupu produktu (M-16: ostatnia dostawa).
            $table->decimal('unit_net_price', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
