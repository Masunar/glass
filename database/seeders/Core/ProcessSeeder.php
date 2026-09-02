<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Enum\Unit;
use App\Models\Process;
use Salvon\Database\Seeder;

/**
 * Czternaście procesów technologicznych wraz z kodami literowymi
 * i czasami trwania — wartości wprost ze słownika starego systemu,
 * potwierdzone niezależnie przyciskami filtrowania w module produkcji.
 *
 * `legacy_id` odpowiada identyfikatorowi ze starej tabeli, dzięki czemu
 * migracja pozycji zleceń mapuje się bez zgadywania.
 *
 * Czasy trwania są tu przeniesione 1:1, ale są stałe niezależnie od
 * wielkości partii — przy zleceniu na sto luster to musi się rozjeżdżać.
 * Stąd puste `setup_minutes` i `unit_minutes`: pola czekają na zmierzone
 * wartości, bo bez nich planowanie pozostaje życzeniowe.
 */
class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        // legacy_id, kod, nazwa, dni, jednostka, podzlecany, z parametrem
        $processes = [
            [1, 'C', 'Cięcie', 2, Unit::RUNNING_METER, false, false],
            [2, 'S', 'Szlif', 3, Unit::RUNNING_METER, false, false],
            [3, 'P', 'Poler', 3, Unit::RUNNING_METER, false, false],
            [4, 'F', 'Fazowanie', 6, Unit::RUNNING_METER, false, true],
            [5, 'R', 'CNC', 12, Unit::RUNNING_METER, false, true],
            [6, 'H', 'Hartownia', 8, Unit::SQUARE_METER, true, false],
            [7, 'L', 'Laminacja', 7, Unit::SQUARE_METER, false, true],
            [8, 'K', 'Lakier', 6, Unit::SQUARE_METER, false, true],
            [9, 'I', 'Inne', 10, Unit::PIECE, false, true],
            [10, 'W', 'Wiercenie', 3, Unit::PIECE, false, false],
            [11, 'O', 'Oprawa obrazu', 9, Unit::PIECE, false, false],
            [12, 'D', 'Wydruk', 2, Unit::SQUARE_METER, false, true],
            [13, 'N', 'Nietypowe', 6, Unit::PIECE, false, true],
            [14, 'M', 'Montaż', 7, Unit::PIECE, false, false],
        ];

        $order = 0;

        foreach ($processes as [$legacyId, $code, $name, $days, $unit, $subcontracted, $parameter]) {
            Process::query()->firstOrCreate(['code' => $code], [
                'code' => $code,
                'name' => $name,
                'unit' => $unit->value,
                'duration_days' => $days,
                'is_subcontracted' => $subcontracted,
                'requires_parameter' => $parameter,
                'default_order' => $order += 10,
                'legacy_id' => $legacyId,
            ]);
        }
    }
}
