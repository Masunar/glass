<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Rejestr ruchow jest zrodlem prawdy, `stock_levels` tylko
        // projekcja. W starym systemie bylo odwrotnie: stan siedzial
        // jako pole w slowniku produktow i ktos je nadpisywal, wiec
        // zlecenie 16492 moglo miec status „Gotowe" przy zerowym stanie
        // okuc i nikt nie umial powiedziec, co sie stalo.
        Schema::create('stock_movements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Lokalizacja zostaje w modelu, chociaż interfejs zna na razie
            // jedna. M-11 jest otwarte, a dolozenie kolumny pozniej
            // znaczyloby przepisanie kazdego ruchu wstecz.
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();

            $table->string('type', 20);

            // Ilosc zawsze dodatnia poza korekta — kierunek niesie typ.
            // Korekta jest jedynym ruchem o obu znakach, bo niesie
            // roznice miedzy spisem a stanem.
            $table->decimal('quantity', 12, 3);

            // Zlecenie, dla ktorego ruch powstal. Rezerwacja bez zlecenia
            // nie ma sensu, ale przyjecie i korekta juz tak.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('document', 40)->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'type']);
            $table->index(['order_id', 'type']);
        });

        // Projekcja: to, co ekran magazynu czyta jednym zapytaniem.
        // Przeliczalna z ruchow w kazdej chwili — i tak ma byc, bo
        // rozjazd miedzy suma a projekcja musi byc wykrywalny.
        Schema::create('stock_levels', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();

            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reserved', 12, 3)->default(0);

            // Progi zamowienia. `Do zamowienia = max(0, Max - Stan)`
            // gdy `Stan < Min` — wzor potwierdzony na siedmiu pozycjach
            // starego systemu (`40-magazyn.md` par. 3.2).
            $table->decimal('min_quantity', 12, 3)->default(0);
            $table->decimal('max_quantity', 12, 3)->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'location_id']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_movements');
    }
};
