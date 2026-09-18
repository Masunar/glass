<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            // Pilne na pozycji, nie na zleceniu: klient czeka na jedna
            // szybe z dwudziestu, a nie na cala dostawe.
            $table->boolean('is_urgent')->default(false)->after('position');

            // Dwa komentarze, bo maja dwoch odbiorcow i nie wolno ich
            // scalic: uwaga handlowa moze trafic na oferte, instrukcja
            // technologiczna idzie na hale i tam nie ma czego szukac
            // zdanie o rabacie.
            $table->string('note', 300)->nullable()->after('is_urgent');
            $table->string('production_note', 300)->nullable()->after('note');
        });

        Schema::table('order_panes', static function (Blueprint $table): void {
            // Nadpisanie minimalnej powierzchni rozliczeniowej dla tej
            // jednej formatki. Puste znaczy "z parametrow wyceny", gdzie
            // stoja dwie wartosci: 0,4 m2 dla hartowanej i 0,1 dla
            // niehartowanej. To nie jest druga prawda obok parametru,
            // tylko wyjatek od niego - i dlatego jest nullowalne.
            $table->decimal('min_billable_m2', 6, 3)->nullable()->after('needs_mark');
        });
    }

    public function down(): void
    {
        Schema::table('order_panes', static function (Blueprint $table): void {
            $table->dropColumn('min_billable_m2');
        });

        Schema::table('order_items', static function (Blueprint $table): void {
            $table->dropColumn(['is_urgent', 'note', 'production_note']);
        });
    }
};
