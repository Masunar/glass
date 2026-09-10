<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Numery dokumentow przydzielane pod blokada wiersza. Bez tego dwoje
        // handlowcow zakladajacych zlecenie w tej samej sekundzie dostaje ten
        // sam numer - a numer zlecenia jest tym, czym klient sie posluguje.
        Schema::create('number_sequences', static function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 30)->unique();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        Schema::create('orders', static function (Blueprint $table): void {
            $table->id();
            // Numer jest osobny od klucza glownego: ciag ze starego systemu
            // trzeba zachowac (klient dzwoni z numerem 23908), a klucze
            // przydziela baza.
            $table->unsignedBigInteger('number')->unique();

            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            // Zlecenie zerowe: dzis relacja miedzy pierwotnym a naprawczym
            // zyje w tresci notatki, a koszt wlasny nigdzie sie nie sumuje.
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('relation_type', 20)->nullable();

            // Dwa pola zamiast kodu O1/O2/M/D: punkt odbioru ma sens wylacznie
            // przy odbiorze wlasnym, bo przy montazu i dowozie jedziemy do klienta.
            $table->string('delivery_method', 20);
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('delivery_address', 200)->nullable();
            $table->string('delivery_contact', 120)->nullable();

            $table->foreignId('invoice_type_id')->nullable()->constrained('invoice_types')->nullOnDelete();
            $table->string('buyer_name', 200)->nullable();
            $table->string('buyer_tax_id', 20)->nullable();
            $table->string('buyer_address', 200)->nullable();
            $table->text('accounting_note')->nullable();

            // Flagi zamiast statusow: te stany nie odbieraja zleceniu miejsca
            // w procesie, wiec nie moga zastepowac informacji, gdzie ono stoi.
            $table->boolean('is_on_hold')->default(false);
            $table->string('hold_reason', 200)->nullable();
            $table->boolean('has_open_claim')->default(false);
            $table->date('agreed_contact_on')->nullable();

            // Cztery komentarze to rozdzielenie odbiorcow, nie nadmiar:
            // tresc dla montazysty nie moze trafic na oferte do klienta.
            $table->string('short_note', 200)->nullable();
            $table->text('production_comment')->nullable();
            $table->text('installer_comment')->nullable();
            $table->text('offer_comment')->nullable();

            // Rozbicie jednego "Na kiedy". W starym systemie prawdziwy termin
            // siedzial w komentarzu ("PRODUKCJA: deadline 15.09"), a system
            // liczyl od innej daty i pokazywal opoznienie, ktorego nie bylo.
            $table->date('client_deadline')->nullable();
            $table->date('production_deadline')->nullable();
            $table->date('shifted_deadline')->nullable();
            $table->string('shift_reason', 200)->nullable();
            $table->foreignId('shift_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cancellation_reason', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('measurement_id')->nullable();
            $table->timestamps();

            $table->index(['status_id', 'client_deadline']);
            $table->index(['contractor_id', 'number']);
            $table->index('production_deadline');
        });

        // Lista obsluguje dwa rozne przypadki naraz: kompozycje (kilka
        // pomieszczen = kilka list, wszystkie wliczone) i wariantowanie oferty
        // (alternatywy, jedna wliczona). Jawne pole `role` zapobiega pomylce
        // "klient dostal sume dwoch alternatyw".
        Schema::create('order_lists', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 120)->nullable();
            $table->string('role', 20)->default('component');
            $table->string('start_type', 20)->nullable();

            // wlaczona != wstrzymana: wylaczona nie nalezy do zlecenia
            // (odrzucona alternatywa, kwota 0), wstrzymana nalezy i jest
            // wyceniona, ale nie moze isc dalej.
            $table->boolean('is_included')->default(true);
            $table->boolean('is_on_hold')->default(false);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'number']);
        });

        Schema::create('order_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_list_id')->constrained('order_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('section', 20);
            $table->string('name', 200);
            $table->decimal('quantity', 12, 3)->default(1);

            // Ceny jako snapshot, nie referencja do cennika: oferta wystawiona
            // wczoraj musi pokazywac te sama kwote po dzisiejszej dostawie.
            $table->decimal('unit_net_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->json('price_path')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['order_list_id', 'position']);
        });

        // Rozszerzenie 1:1 dla pozycji szklanych. Wymiary i ksztalt nie maja
        // sensu przy usludze transportu.
        Schema::create('order_panes', static function (Blueprint $table): void {
            $table->foreignId('order_item_id')->primary()->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->boolean('is_irregular_shape')->default(false);
            $table->boolean('is_tempered')->default(false);
            // Trwale oznaczenie wypalane podczas hartowania - jedyna rzecz,
            // ktora trzeba zglosic hartowni przed wsadem (Z-06/H-01).
            $table->boolean('needs_mark')->default(false);
            $table->foreignId('pane_template_id')->nullable()->constrained('pane_templates')->nullOnDelete();
        });

        Schema::create('order_item_processes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->restrictOnDelete();
            // RAL 9003, faza 15 mm, rodzaj folii
            $table->string('parameter', 60)->nullable();
            $table->decimal('unit_net_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['order_item_id', 'position']);
        });

        // Poziom 4 ustalania ceny: rabat na zleceniu, osobny per sekcja
        // asortymentu - tak samo jak sekcje cenowe kontrahenta.
        Schema::create('order_discounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('section', 20);
            $table->decimal('percent', 5, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('order_item_processes');
        Schema::dropIfExists('order_panes');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_lists');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('number_sequences');
    }
};
