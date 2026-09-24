<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            // Prowadzacy to nie zakladajacy. `created_by` odpowiada na
            // pytanie „kto to wpisal" i po roku nie znaczy juz nic —
            // `owner_id` odpowiada na „kogo pytac o to zlecenie dzisiaj"
            // i dlatego musi byc zmienialny. Jedno pole w dwoch rolach
            // konczy sie tym, ze zmiana opiekuna falszuje historie.
            $table->foreignId('owner_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            // Filtr „moje" chodzi po tej kolumnie przy kazdym wejsciu na
            // liste, razem z pasmem terminu.
            $table->index(['owner_id', 'status_id']);
        });

        // Zlecenia sprzed tej zmiany maja tylko zakladajacego. Pusty
        // prowadzacy znaczylby „niczyje" przy kazdym starym zleceniu,
        // a to nieprawda: dopoki nikt nie przekazal zlecenia dalej,
        // odpowiada ten, kto je zalozyl.
        DB::table('orders')->whereNull('owner_id')->update([
            'owner_id' => DB::raw('created_by'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'status_id']);
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
