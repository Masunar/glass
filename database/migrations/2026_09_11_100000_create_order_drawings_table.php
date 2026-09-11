<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Rysunek nalezy do zlecenia, a do formatki tylko wtedy, gdy ktos
        // to wskaze. Wymuszanie wyboru pozycji przy kazdym pliku konczy sie
        // przypisywaniem szkicu calego pomieszczenia do pierwszej z brzegu
        // formatki, bo formularz nie przepuszcza pustego pola.
        Schema::create('order_drawings', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            // Nazwa od uzytkownika i sciezka na dysku to dwie rozne rzeczy:
            // plik zapisujemy pod wygenerowana nazwa, zeby dwa "rysunek.pdf"
            // sie nie nadpisaly, a czlowiekowi pokazujemy to, co wgral.
            $table->string('original_name', 200);
            $table->string('stored_path', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('note', 200)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'id']);
        });

        Schema::table('orders', static function (Blueprint $table): void {
            // "Wszystkie rysunki dodane" to nie checkbox, tylko oswiadczenie
            // konkretnej osoby z data. Produkcja rusza na jego podstawie,
            // wiec musi byc wiadomo, kto je zlozyl — sam znacznik logiczny
            // nie odpowiada na zadne pytanie zadane po fakcie.
            $table->foreignId('drawings_complete_by')
                ->nullable()
                ->after('measurement_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('drawings_complete_at')->nullable()->after('drawings_complete_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('drawings_complete_by');
            $table->dropColumn('drawings_complete_at');
        });

        Schema::dropIfExists('order_drawings');
    }
};
