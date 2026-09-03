<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_groups', static function (Blueprint $table): void {
            $table->id();
            // sekcja asortymentu: glass | fittings | services | other
            $table->string('section', 20);
            $table->string('name', 100);
            $table->string('manufacturer', 100)->nullable();
            $table->string('series', 100)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['section', 'name']);
            $table->unique(['section', 'legacy_id']);
            $table->index(['section', 'is_active', 'name']);
        });

        // Jedna kartoteka dla wszystkich pieciu sekcji. Cennik, ceny
        // indywidualne kontrahentow, rabaty na zleceniu i stany magazynowe
        // odnosza sie do jednego bytu - stad dyskryminator, a nie piec
        // rownoleglych tabel produktow.
        Schema::create('products', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_group_id')->constrained('product_groups')->restrictOnDelete();
            $table->string('section', 20);

            $table->string('code', 40)->nullable();
            $table->string('manufacturer_code', 40)->nullable();
            $table->string('name', 150);

            $table->string('unit', 8);
            $table->unsignedTinyInteger('vat_rate')->default(23);

            // produkt katalogowy sprowadzany pod zamowienie, nietrzymany
            // na stanie - zeby nie zasmiecal listy brakow
            $table->boolean('is_made_to_order')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['section', 'legacy_id']);
            $table->index(['section', 'is_active']);
            $table->index('code');
            $table->index('name');
        });

        // Rozszerzenia 1:1, tylko pola wlasciwe dla danej sekcji.
        Schema::create('product_glass', static function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->decimal('thickness_mm', 6, 2);
            // wariant materialu jako osobne pole. Stary system kodowal go
            // w czesci dziesietnej grubosci: 4 = float, 4,05 = ornament,
            // 4,4 = VSG - dzialalo, dopoki nie doszedl trzeci wariant 4 mm.
            $table->string('variant', 30)->nullable();
            $table->boolean('is_tempered_by_default')->default(false);
        });

        Schema::create('product_fittings', static function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('finish', 40)->nullable();
            $table->string('dimension', 40)->nullable();
        });

        Schema::create('product_services', static function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->foreignId('process_id')->nullable()->constrained('processes')->nullOnDelete();
            // cena procesu zalezy od grubosci obrabianego szkla
            $table->decimal('glass_thickness_mm', 6, 2)->nullable();
            $table->string('variant', 30)->nullable();
        });

        // Cena zakupu wersjonowana i trzymana osobno od cennika sprzedazy.
        // To rozdzielenie kosztu ewidencyjnego od ceny cennikowej: dostawa
        // po nowej cenie nie moze po cichu przeliczyc cen na ofertach.
        Schema::create('purchase_prices', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('net_price', 12, 2);
            $table->string('source', 20);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_prices');
        Schema::dropIfExists('product_services');
        Schema::dropIfExists('product_fittings');
        Schema::dropIfExists('product_glass');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_groups');
    }
};
