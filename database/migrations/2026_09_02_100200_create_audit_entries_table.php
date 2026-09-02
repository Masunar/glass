<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Rejestr tylko do dopisywania. Wpisu nie edytujemy i nie kasujemy -
        // stad brak updated_at i brak miekkiego usuwania.
        Schema::create('audit_entries', static function (Blueprint $table): void {
            $table->id();

            // Zmiany z jednej sesji edycji dziela ten identyfikator i sa
            // prezentowane jako jeden wpis. W starym systemie log potrafil
            // miec 21 pozycji "Zmienil rabaty" w ciagu 25 minut.
            $table->uuid('edit_session_id')->nullable();

            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);

            // [{"field": "...", "before": ..., "after": ...}]
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_entries_auditable_index');
            $table->index('edit_session_id');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
