<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Enum\PaneShape;
use App\Models\Process;
use Illuminate\Database\Eloquent\Builder;

/**
 * Czy zlecenie potrzebuje rysunków — i dlaczego.
 *
 * Dotąd rysunki były wymagane od każdego zlecenia: alert „brak rysunków"
 * zapalał się przy prostych docinkach, a przejście do produkcji czekało
 * na oświadczenie, którego nie było czym uzasadnić. Rysunek jest
 * potrzebny, gdy **na liście wliczonej do zlecenia** jest formatka
 * z kształtem albo owalem, albo proces oznaczony w słowniku jako
 * „wymaga rysunku" (decyzja Marcina, 24.09).
 *
 * **Jedna reguła, dwie postacie.** Alert pyta bazę o wszystkie zlecenia
 * naraz (`scope()`), warunek przejścia pyta o jedno zlecenie, które ma
 * już wczytane pozycje (`reasons()`). Obie postacie opisują ten sam
 * zbiór i test pilnuje, żeby się nie rozjechały — alert mówiący „brak
 * rysunków" przy zleceniu, które przechodzi do produkcji bez nich, byłby
 * dokładnie tym rozjazdem.
 *
 * Wariant niewliczony (alternatywa z oferty) nie wymaga rysunku: nie
 * pojedzie na halę, więc nie ma czego z niego wycinać.
 */
final class DrawingRequirement
{
    /**
     * Procesy z rysunkiem: id => nazwa. Słownik jest mały, a pytanie pada
     * przy każdym wierszu listy — jedno zapytanie na obiekt.
     *
     * @var array<int, string>|null
     */
    private ?array $processes = null;

    /**
     * Zlecenia wymagające rysunków — zawężenie zapytania.
     *
     * @param Builder<Order> $orders
     * @return Builder<Order>
     */
    public function scope(Builder $orders): Builder
    {
        return $orders->whereHas('lists', static fn(Builder $list): Builder => $list
            ->where('is_included', true)
            ->whereHas('items', static fn(Builder $item): Builder => $item->where(
                static fn(Builder $any): Builder => $any
                    ->whereHas(
                        'pane',
                        static fn(Builder $pane): Builder => $pane->where('shape', '!=', PaneShape::RECTANGLE->value),
                    )
                    ->orWhereHas(
                        'processes.process',
                        static fn(Builder $process): Builder => $process->where('requires_drawing', true),
                    ),
            )));
    }

    /**
     * Powody, dla których zlecenie wymaga rysunków. Pusta lista — nie
     * wymaga.
     *
     * Zdania, nie kody: pokazują się na zakładce rysunków obok
     * oświadczenia, żeby było wiadomo, czego rysunek ma dotyczyć.
     *
     * @return list<string>
     */
    public function reasons(Order $order): array
    {
        $order->loadMissing(['lists.items.pane', 'lists.items.processes']);

        $withDrawing = $this->processes();
        $shapes = [];
        $named = [];

        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            foreach ($list->items as $item) {
                $shape = $item->pane?->shape;

                if ($shape !== null && $shape->needsDrawing()) {
                    $shapes[$shape->value] = ($shapes[$shape->value] ?? 0) + 1;
                }

                foreach ($item->processes as $entry) {
                    $id = (int) $entry->process_id;

                    if (isset($withDrawing[$id])) {
                        $named[$id] = $withDrawing[$id];
                    }
                }
            }
        }

        $reasons = [];

        foreach ([PaneShape::IRREGULAR, PaneShape::OVAL] as $shape) {
            $count = $shapes[$shape->value] ?? 0;

            if ($count > 0) {
                $reasons[] = sprintf('%s: %d %s', $shape->label(), $count, self::panes($count));
            }
        }

        foreach ($named as $name) {
            $reasons[] = $name;
        }

        return $reasons;
    }

    public function requires(Order $order): bool
    {
        return $this->reasons($order) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function processes(): array
    {
        if ($this->processes === null) {
            /** @var array<int, string> $names */
            $names = Process::query()
                ->where('requires_drawing', true)
                ->orderBy('default_order')
                ->pluck('name', 'id')
                ->all();

            $this->processes = $names;
        }

        return $this->processes;
    }

    /** „formatka", „formatki", „formatek" — polska odmiana po liczbie. */
    private static function panes(int $count): string
    {
        if ($count === 1) {
            return 'formatka';
        }

        $tens = $count % 100;
        $units = $count % 10;

        return $units >= 2 && $units <= 4 && ($tens < 12 || $tens > 14) ? 'formatki' : 'formatek';
    }
}
