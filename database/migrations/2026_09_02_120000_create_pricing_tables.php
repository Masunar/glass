<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_sections', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('section', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['section', 'name']);
            $table->index(['section', 'position']);
        });

        // Limit rabatu jako WIERSZ rola x sekcja cenowa, nie kolumna.
        // Stary system mial kolumny price_admin / price_sh / price_h, wiec
        // rol sprzedazowych moglo byc dokladnie trzy - a ról jest osiem
        // i dodanie kolejnej wymagaloby zmiany schematu tabeli.
        Schema::create('role_discount_limits', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_section_id')->constrained('price_sections')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->decimal('max_discount_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['price_section_id', 'role_id']);
        });

        // Cennik: cena = cena zakupu x wspolczynnik narzutu.
        // Cennik nie jest tabela liczb, tylko polityka marzy - stad
        // wspolczynnik jest wartoscia wiodaca, a cena wynikowa pochodna.
        Schema::create('price_list_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('price_section_id')->constrained('price_sections')->cascadeOnDelete();

            $table->decimal('coefficient', 6, 4);
            $table->decimal('computed_net_price', 12, 2)->nullable();
            // reczne nadpisanie ceny wyliczonej; puste znaczy "licz ze wspolczynnika"
            $table->decimal('manual_net_price', 12, 2)->nullable();

            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'price_section_id', 'valid_from'], 'price_list_items_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('role_discount_limits');
        Schema::dropIfExists('price_sections');
    }
};
