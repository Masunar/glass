<?php

declare(strict_types=1);

namespace App\Simulation;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Pomiar jednego wywołania: czas i liczba zapytań do bazy.
 *
 * Liczba zapytań jest ważniejsza niż czas. Czas zależy od maszyny,
 * obciążenia i rozgrzania bazy; zapytania rosnące razem z liczbą zleceń
 * to błąd w kodzie, który na produkcji wyjdzie tak samo, tylko później.
 */
final class Probe
{
    /**
     * @template T
     * @param Closure(): T $call
     * @return array{ms: float, queries: int, result: T}
     */
    public function measure(Closure $call): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $started = hrtime(true);

        try {
            $result = $call();
        } finally {
            $elapsed = (hrtime(true) - $started) / 1_000_000;
            $queries = count(DB::getQueryLog());

            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return ['ms' => round($elapsed, 1), 'queries' => $queries, 'result' => $result];
    }
}
