<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Rodzaj kształtu formatki zamiast flagi „nieregularny" i flaga
 * „wymaga rysunku" przy procesie.
 *
 * Flaga kształtu jest przepisywana na rodzaj i **usuwana**: zostawiona
 * obok byłaby drugim zapisem tej samej rzeczy, który rozjedzie się przy
 * pierwszym owalu.
 *
 * Procesy z rysunkiem na start (decyzja Marcina): CNC, Wiercenie,
 * Nietypowe i Inne — po kodzie, bo nazwy w słowniku da się zmienić.
 */
return new class extends Migration
{
    private const DRAWING_PROCESSES = ['R', 'W', 'N', 'I'];

    public function up(): void
    {
        Schema::table('order_panes', static function (Blueprint $table): void {
            $table->string('shape', 12)->default('rectangle')->after('height_mm');
        });

        DB::table('order_panes')->where('is_irregular_shape', true)->update(['shape' => 'irregular']);

        Schema::table('order_panes', static function (Blueprint $table): void {
            $table->dropColumn('is_irregular_shape');
        });

        Schema::table('processes', static function (Blueprint $table): void {
            $table->boolean('requires_drawing')->default(false)->after('requires_parameter');
        });

        DB::table('processes')->whereIn('code', self::DRAWING_PROCESSES)->update(['requires_drawing' => true]);
    }

    public function down(): void
    {
        Schema::table('order_panes', static function (Blueprint $table): void {
            $table->boolean('is_irregular_shape')->default(false)->after('height_mm');
        });

        // Owal wraca jako kształt — flaga nie ma trzeciej wartości.
        DB::table('order_panes')->where('shape', '!=', 'rectangle')->update(['is_irregular_shape' => true]);

        Schema::table('order_panes', static function (Blueprint $table): void {
            $table->dropColumn('shape');
        });

        Schema::table('processes', static function (Blueprint $table): void {
            $table->dropColumn('requires_drawing');
        });
    }
};
