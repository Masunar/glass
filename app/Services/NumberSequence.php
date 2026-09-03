<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Przydzielanie numerów dokumentów.
 *
 * Numer zlecenia jest tym, czym posługuje się klient przez telefon, więc
 * dwa zlecenia z tym samym numerem to realny problem, a nie kosmetyka.
 * Stąd blokada wiersza w transakcji zamiast `max(numer) + 1`, które przy
 * dwóch handlowcach zapisujących w tej samej sekundzie zwraca tę samą
 * wartość obu.
 *
 * Ciąg jest przenoszony ze starego systemu: numeracja ma być
 * kontynuowana od pierwszego wolnego numeru, a nie zaczynana od nowa.
 */
final readonly class NumberSequence
{
    public const ORDERS = 'orders';

    public function next(string $domain, int $floor = 1): int
    {
        return DB::transaction(function () use ($domain, $floor): int {
            $row = DB::table('number_sequences')
                ->where('domain', $domain)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('number_sequences')->insert([
                    'domain' => $domain,
                    'next_value' => $floor + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $floor;
            }

            $value = (int) $row->next_value;

            DB::table('number_sequences')
                ->where('domain', $domain)
                ->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $value;
        });
    }

    /**
     * Ustawienie ciągu po migracji danych.
     *
     * Wywoływane po wgraniu zleceń ze starego systemu, żeby nowe numery
     * zaczęły się od pierwszego wolnego, a nie nadpisały istniejących.
     */
    public function seedFrom(string $domain, int $lastUsedNumber): void
    {
        DB::table('number_sequences')->updateOrInsert(
            ['domain' => $domain],
            ['next_value' => $lastUsedNumber + 1, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
