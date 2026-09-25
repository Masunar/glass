<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Od kiedy zlecenie stoi w obecnym statusie.
 *
 * Bez tej daty reguła „za długo w statusie" nie ma czego liczyć.
 * Uzupełnienie wstecz bierze ostatnią zmianę statusu z dziennika,
 * a gdy jej nie ma (zlecenie nigdy nie zmieniło statusu) — datę
 * założenia, bo od założenia stoi w pierwszym statusie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->timestamp('status_changed_at')->nullable()->after('status_id');
            $table->index(['status_id', 'status_changed_at']);
        });

        DB::statement(
            'UPDATE orders SET status_changed_at = COALESCE('
            . '(SELECT MAX(audit_entries.created_at) FROM audit_entries'
            . ' WHERE audit_entries.auditable_type = ?'
            . ' AND audit_entries.auditable_id = orders.id'
            . " AND audit_entries.event = 'status_changed'),"
            . ' orders.created_at)',
            [Order::class],
        );
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex(['status_id', 'status_changed_at']);
            $table->dropColumn('status_changed_at');
        });
    }
};
