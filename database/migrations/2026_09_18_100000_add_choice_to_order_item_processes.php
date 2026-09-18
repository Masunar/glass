<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_processes', static function (Blueprint $table): void {
            // Wybrana pozycja cennikowa procesu. Grubosc szkla zawezajaca
            // liste nie rozstrzyga jej: fazowanie ma dla jednej grubosci
            // osiem wierszy (faza 5..40 mm), CNC cztery niezalezne od
            // grubosci. Bez tej kolumny system bral pierwszy z brzegu
            // i cicho wstawial cene innego wariantu.
            $table->foreignId('product_id')->nullable()->after('process_id')
                ->constrained('products')->nullOnDelete();

            // Czas trwania etapu w dniach. Domyslnie ze slownika procesow,
            // ale wpisywany recznie przy pozycji - dlatego snapshot, a nie
            // odczyt z `processes.duration_days` przy kazdym wyswietleniu.
            $table->unsignedSmallInteger('days')->nullable()->after('parameter');
            $table->string('comment', 300)->nullable()->after('days');
        });

        Schema::table('production_tasks', static function (Blueprint $table): void {
            // Jednostka pracy to linia marszruty, nie para pozycja+proces:
            // na jednej szybie moga byc dwie rozne fazy i sa to dwa osobne
            // zadania dla hali.
            //
            // `nullOnDelete`, nie kaskada. Wiersze marszruty sa kasowane
            // i zakladane od nowa przy kazdym zapisie formatki, bo cena
            // jest snapshotem - kaskada kasowalaby przy tym historie pracy
            // hali, czyli dokladnie to, przed czym osobna tabela zadan ma
            // chronic. Zadanie zostaje i przy synchronizacji dostaje
            // wskazanie na nowy wiersz.
            $table->foreignId('order_item_process_id')->nullable()->after('order_item_id')
                ->constrained('order_item_processes')->nullOnDelete();

            // Wybrana pozycja cennikowa jako czesc tozsamosci zadania.
            $table->foreignId('product_id')->nullable()->after('process_id')
                ->constrained('products')->nullOnDelete();
        });

        // Tozsamosc zadania to pozycja + proces + wybrana pozycja
        // cennikowa: faza 15 mm i faza 25 mm na jednej szybie to dwie
        // rozne prace. Klucz przezywa przepisanie marszruty, bo nie
        // opiera sie na jej identyfikatorze.
        //
        // Najpierw zakladamy nowy indeks, dopiero potem kasujemy stary.
        // Na starym wisi klucz obcy `order_item_id` i MariaDB nie pozwoli
        // go zdjac, dopoki nie ma czym go zastapic - a nowy zaczyna sie
        // od tej samej kolumny, wiec przejmuje te role.
        Schema::table('production_tasks', static function (Blueprint $table): void {
            $table->unique(['order_item_id', 'process_id', 'product_id'], 'production_tasks_route_unique');
        });

        Schema::table('production_tasks', static function (Blueprint $table): void {
            $table->dropUnique(['order_item_id', 'process_id']);
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', static function (Blueprint $table): void {
            $table->unique(['order_item_id', 'process_id']);
            $table->dropUnique('production_tasks_route_unique');
            $table->dropConstrainedForeignId('order_item_process_id');
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('order_item_processes', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['days', 'comment']);
        });
    }
};
