<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Simulation\ScaleCheck;
use Illuminate\Console\Command;
use App\Simulation\OrderSimulator;
use Illuminate\Support\Facades\Storage;

/**
 * Symulacja dużej bazy: zakładanie zleceń i pomiar ekranów.
 *
 * Odpalana ręcznie (`make simulate`), nigdy z zasiewu — dziesięć tysięcy
 * zleceń to kilka minut, a codzienne przesianie ma zostać tak szybkie
 * jak dziś. Dane zostają w bazie deweloperskiej; małe demo przywraca
 * `make migrate-fresh-seed`.
 *
 * Raport idzie do konsoli i do pliku Markdown w `storage/app/symulacja/`,
 * żeby dało się go porównać z następnym przebiegiem po poprawce.
 */
class SimulateOrders extends Command
{
    protected $signature = 'glass:simulate
        {--orders=10000 : Ile zleceń założyć}
        {--seed= : Ziarno losowania — ten sam przebieg da się powtórzyć}
        {--measure-only : Tylko pomiar, bez zakładania}';

    protected $description = 'Zakłada zlecenia drogą ekranu i mierzy ekrany na dużej bazie.';

    public function handle(OrderSimulator $simulator, ScaleCheck $check): int
    {
        $count = max(0, (int) $this->option('orders'));
        $seedOption = $this->option('seed');
        $seed = is_numeric($seedOption) ? (int) $seedOption : random_int(1, 999_999);

        $created = null;

        if (!$this->option('measure-only') && $count > 0) {
            $this->info(sprintf('Zakładanie %d zleceń (ziarno %d)…', $count, $seed));

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $created = $simulator->run($count, $seed, static function () use ($bar): void {
                $bar->advance();
            });

            $bar->finish();
            $this->newLine(2);
        }

        $this->info('Pomiar ekranów…');
        $result = $check->run();

        $report = $this->markdown($created, $seed, $result);
        $path = 'symulacja/raport-' . Carbon::now()->format('Ymd-His') . '.md';
        Storage::disk('local')->put($path, $report);

        $this->render($created, $result);
        $this->newLine();
        $this->line('Raport: storage/app/' . $path);

        return self::SUCCESS;
    }

    /**
     * @param array{orders: int, lists: int, panes: int, rejected: array<string, int>, seconds: float}|null $created
     * @param array{timings: list<array{screen: string, ms: float, queries: int, note: string}>, gaps: list<array{what: string, screen: int|null, truth: int, note: string}>, counts: array{orders: int, items: int}, rules: list<array{code: string, matched: int, find_ms: float, find_queries: int, subjects_ms: float, subjects_queries: int, open_ms: float, open_queries: int}>} $result
     */
    private function render(?array $created, array $result): void
    {
        if ($created !== null) {
            $this->line(sprintf(
                'Założone: %d zleceń, %d list, %d formatek w %.1f s. Odrzucone formatki: %s.',
                $created['orders'],
                $created['lists'],
                $created['panes'],
                $created['seconds'],
                $this->rejected($created['rejected']),
            ));
        }

        $this->line(sprintf(
            'W bazie: %d zleceń, %d pozycji.',
            $result['counts']['orders'],
            $result['counts']['items'],
        ));

        $this->table(
            ['Ekran', 'ms', 'Zapytania', 'Uwagi'],
            array_map(
                static fn(array $row): array => [$row['screen'], $row['ms'], $row['queries'], $row['note']],
                $result['timings'],
            ),
        );

        $this->table(
            ['Liczba', 'Ekran', 'Baza', '', 'Skąd'],
            array_map(
                fn(array $row): array => [
                    $row['what'],
                    $row['screen'] ?? '—',
                    $row['truth'],
                    $this->verdict($row),
                    $row['note'],
                ],
                $result['gaps'],
            ),
        );

        $this->table(
            ['Reguła', 'Zapaliła', 'Warunek ms', 'zap.', 'Podpisy (5) ms', 'zap.', 'Otwarte ms', 'zap.'],
            array_map(
                static fn(array $row): array => [
                    $row['code'],
                    $row['matched'],
                    $row['find_ms'],
                    $row['find_queries'],
                    $row['subjects_ms'],
                    $row['subjects_queries'],
                    $row['open_ms'],
                    $row['open_queries'],
                ],
                $result['rules'],
            ),
        );
    }

    /**
     * @param array{orders: int, lists: int, panes: int, rejected: array<string, int>, seconds: float}|null $created
     * @param array{timings: list<array{screen: string, ms: float, queries: int, note: string}>, gaps: list<array{what: string, screen: int|null, truth: int, note: string}>, counts: array{orders: int, items: int}, rules: list<array{code: string, matched: int, find_ms: float, find_queries: int, subjects_ms: float, subjects_queries: int, open_ms: float, open_queries: int}>} $result
     */
    private function markdown(?array $created, int $seed, array $result): string
    {
        $lines = [
            '# Symulacja dużej bazy — ' . Carbon::now()->format('Y-m-d H:i'),
            '',
        ];

        if ($created !== null) {
            $lines[] = sprintf(
                'Założone: **%d zleceń**, %d list, %d formatek w %.1f s (ziarno %d). Odrzucone formatki: %s.',
                $created['orders'],
                $created['lists'],
                $created['panes'],
                $created['seconds'],
                $seed,
                $this->rejected($created['rejected']),
            );
            $lines[] = '';
        }

        $lines[] = sprintf(
            'W bazie: %d zleceń, %d pozycji.',
            $result['counts']['orders'],
            $result['counts']['items'],
        );
        $lines[] = '';
        $lines[] = '> Rozkład statusów, wymiary i liczba list są **założeniem symulacji**, nie odczytem'
            . ' z produkcji. Kontrahenci, konta, materiały i ceny pochodzą z bazy i z cennika.';
        $lines[] = '';
        $lines[] = '## Czasy';
        $lines[] = '';
        $lines[] = '| Ekran | ms | Zapytania | Uwagi |';
        $lines[] = '|---|---:|---:|---|';

        foreach ($result['timings'] as $row) {
            $lines[] = sprintf('| %s | %.1f | %d | %s |', $row['screen'], $row['ms'], $row['queries'], $row['note']);
        }

        $lines[] = '';
        $lines[] = '## Rozjazdy — liczba na ekranie a baza';
        $lines[] = '';
        $lines[] = '| Liczba | Ekran | Baza | | Skąd |';
        $lines[] = '|---|---:|---:|---|---|';

        foreach ($result['gaps'] as $row) {
            $lines[] = sprintf(
                '| %s | %s | %d | %s | %s |',
                $row['what'],
                $row['screen'] ?? '—',
                $row['truth'],
                $this->verdict($row),
                $row['note'],
            );
        }

        $lines[] = '';
        $lines[] = '## Reguły alertów — gdzie idzie czas przebiegu';
        $lines[] = '';
        $lines[] = 'Same odczyty: warunek, podpisy, otwarte wystąpienia. Różnica do czasu całego'
            . ' przebiegu to uzgadnianie wystąpień i składanie wierszy.';
        $lines[] = '';
        $lines[] = '| Reguła | Zapaliła | Warunek ms | zap. | Podpisy (5) ms | zap. | Otwarte ms | zap. |';
        $lines[] = '|---|---:|---:|---:|---:|---:|---:|---:|';

        foreach ($result['rules'] as $row) {
            $lines[] = sprintf(
                '| %s | %d | %.1f | %d | %.1f | %d | %.1f | %d |',
                $row['code'],
                $row['matched'],
                $row['find_ms'],
                $row['find_queries'],
                $row['subjects_ms'],
                $row['subjects_queries'],
                $row['open_ms'],
                $row['open_queries'],
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array{what: string, screen: int|null, truth: int, note: string} $row
     */
    private function verdict(array $row): string
    {
        if ($row['screen'] === null) {
            return 'brak dostępu';
        }

        return $row['screen'] === $row['truth'] ? 'zgodne' : 'ROZJAZD';
    }

    /**
     * @param array<string, int> $rejected
     */
    private function rejected(array $rejected): string
    {
        if ($rejected === []) {
            return 'żadna';
        }

        $parts = [];

        foreach ($rejected as $field => $count) {
            $parts[] = $field . ' × ' . $count;
        }

        return implode(', ', $parts);
    }
}
