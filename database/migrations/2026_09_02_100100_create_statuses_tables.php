<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('statuses', static function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 30);
            $table->string('code', 40);
            $table->string('name', 60);
            $table->string('short_name', 20)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('color', 7)->nullable();

            // status nadawany przy utworzeniu bytu w tej dziedzinie
            $table->boolean('is_default')->default(false);
            // status koncowy - nie prowadzi juz nigdzie dalej
            $table->boolean('is_final')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'code']);
            $table->unique(['domain', 'legacy_id']);
            $table->index(['domain', 'position']);
        });

        Schema::create('status_transitions', static function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 30);

            // null = przejscie poczatkowe, czyli nadanie pierwszego statusu
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->cascadeOnDelete();

            // warunki deklaratywne, sprawdzane przed przejsciem. Kazdy warunek
            // niesie wlasny komunikat, zeby zablokowane przejscie odpowiadalo
            // na pytanie "czego brakuje i gdzie to uzupelnic".
            $table->json('conditions')->nullable();

            $table->string('button_label', 60)->nullable();
            $table->string('required_permission', 60)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id']);
            $table->index(['domain', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
        Schema::dropIfExists('statuses');
    }
};
