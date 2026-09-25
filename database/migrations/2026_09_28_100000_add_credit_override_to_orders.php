<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Limit kupiecki blokuje produkcję, zgoda administratora go omija.
 *
 * Do tej pory do produkcji wpuszczała zaliczka **albo** mieszczenie się
 * w limicie — tysiąc złotych wpłaty przepuszczało kontrahenta 2,6 mln
 * ponad limitem. Decyzja Marcina (25.09): ponad limitem blokuje zawsze,
 * a administrator może przepchnąć zlecenie ze zgodą, powodem i śladem,
 * kto i kiedy.
 *
 * Warunek w `status_transitions` jest daną, nie kodem, więc migracja
 * podmienia go w zapisanych przejściach — seeder nie rusza istniejących.
 */
return new class extends Migration
{
    private const OLD = 'prepayment_or_credit_limit';

    private const NEW = [
        'rule' => 'credit_limit',
        'message' => 'Kontrahent przekracza limit kupiecki — przekazanie wymaga zgody administratora.',
    ];

    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->foreignId('credit_override_by')->nullable()->after('owner_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('credit_override_at')->nullable()->after('credit_override_by');
            $table->string('credit_override_reason', 300)->nullable()->after('credit_override_at');
        });

        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW['rule'], [
            'rule' => self::OLD,
            'message' => 'Brak zaliczki, a kontrahent nie mieści się w limicie kredytowym.',
        ]);

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credit_override_by');
            $table->dropColumn(['credit_override_at', 'credit_override_reason']);
        });
    }

    /**
     * @param array{rule: string, message: string} $replacement
     */
    private function swap(string $rule, array $replacement): void
    {
        $rows = DB::table('status_transitions')->whereNotNull('conditions')->get(['id', 'conditions']);

        foreach ($rows as $row) {
            $conditions = json_decode((string) $row->conditions, true);

            if (!is_array($conditions)) {
                continue;
            }

            $changed = false;

            foreach ($conditions as $index => $condition) {
                if (is_array($condition) && ($condition['rule'] ?? null) === $rule) {
                    $conditions[$index] = $replacement;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('status_transitions')
                    ->where('id', $row->id)
                    ->update(['conditions' => json_encode($conditions, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
