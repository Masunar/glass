<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Szablon powtarzalnej formatki. Mechanizm wart zachowania -
        // powtarzalne zamowienie wprowadza sie jednym klikniecieniem
        // zamiast przepisywac wymiary.
        Schema::create('pane_templates', static function (Blueprint $table): void {
            $table->id();
            // nazwa wymagana: w starym systemie biblioteka zasmiecila sie
            // czterema pozycjami o nazwie "nowa formatka"
            $table->string('name', 120);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->decimal('min_billable_m2', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'last_used_at']);
        });

        // Rozdzielenie szablonu wielokrotnego uzytku od konfiguracji
        // zapisanej pod konkretnego klienta. W starym systemie oba byty
        // dzielily jedna liste, wiec obok "System przesuwny terno clear"
        // lezaly pozycje "23) Marta Grabowska" (dwa razy), "140", "test".
        Schema::create('fitting_sets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->foreignId('customer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::create('fitting_set_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fitting_set_id')->constrained('fitting_sets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['fitting_set_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitting_set_items');
        Schema::dropIfExists('fitting_sets');
        Schema::dropIfExists('pane_templates');
    }
};
