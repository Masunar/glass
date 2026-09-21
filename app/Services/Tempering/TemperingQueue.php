<?php

declare(strict_types=1);

namespace App\Services\Tempering;

use App\Enum\Section;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TemperingItem;
use App\Enum\TemperingItemStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kolejka do hartowni: co czeka na wysłanie.
 *
 * **Do kolejki kwalifikuje znacznik „hartowane" na formatce** (H-04),
 * a nie etap H na marszrucie. Konsekwencja jest świadoma i zapisana
 * jako H-11: szyba z zaznaczonym hartowaniem **i** etapem H pojawi się
 * w dwóch miejscach — tutaj i w kolejce hali. Sprzęgnięcie tych dwóch
 * na ślepo byłoby gorsze niż widoczny rozdział.
 *
 * Kolejka jest **uzgadniana, nie dopisywana**. Ponowne wejście zlecenia
 * na produkcję po poprawce nie zakłada drugiego kompletu pozycji, tak
 * samo jak rezerwacje okuć.
 */
final readonly class TemperingQueue
{
    /**
     * Uzgodnienie kolejki z tym, co wynika ze zlecenia teraz.
     *
     * Pokrycie liczy pozycje, które **mają szansę wrócić**: w kolejce,
     * wysłane, te, które wróciły, i te do poprawki. Stłuczka i brak nie
     * pokrywają niczego — dlatego same z siebie odtwarzają niedobór
     * i następne uzgodnienie dopisze pozycję zastępczą.
     *
     * @return list<TemperingItem> pozycje dopisane w tym wywołaniu
     */
    public function sync(Order $order): array
    {
        $created = [];

        foreach ($this->qualifying($order) as $item) {
            $needed = (float) $item->quantity;
            $covered = $this->covered($item);
            $difference = round($needed - $covered, 3);

            if ($difference > 0) {
                $created[] = $this->enqueue($item, $difference);

                continue;
            }

            if ($difference < 0) {
                $this->trim($item, -$difference);
            }
        }

        return $created;
    }

    /**
     * Zdjęcie z kolejki tego, co jeszcze nie pojechało.
     *
     * Wywoływane przy anulowaniu zlecenia. Pozycji wysłanych nie
     * ruszamy — szkło jest u podwykonawcy i wróci niezależnie od tego,
     * co się stało ze zleceniem.
     */
    public function release(Order $order): int
    {
        return TemperingItem::query()
            ->where('status', TemperingItemStatus::QUEUED->value)
            ->whereNull('tempering_batch_id')
            ->whereIn('order_item_id', $this->itemIds($order))
            ->delete();
    }

    /**
     * Pozycja zastępcza po stłuczce albo braku.
     *
     * Wskazuje na tę, która się stłukła, bo inaczej po miesiącu nie da
     * się powiedzieć, dlaczego ta sama formatka jechała dwa razy.
     */
    public function replace(TemperingItem $broken): TemperingItem
    {
        /** @var TemperingItem */
        return TemperingItem::query()->create([
            'order_item_id' => $broken->order_item_id,
            'status' => TemperingItemStatus::QUEUED->value,
            'quantity' => $broken->quantity,
            'replaces_id' => $broken->getKey(),
        ]);
    }

    /**
     * Formatki zlecenia oznaczone jako hartowane.
     *
     * Alternatywy odpadają razem z całą listą: nie wchodzą ani do
     * produkcji, ani do kwoty, więc nie mają po co jechać do pieca.
     *
     * @return iterable<OrderItem>
     */
    private function qualifying(Order $order): iterable
    {
        /** @var iterable<OrderItem> */
        return OrderItem::query()
            ->with(['pane', 'product.glass'])
            ->where('section', Section::GLASS->value)
            ->whereHas('pane', static fn(Builder $query): Builder => $query->where('is_tempered', true))
            ->whereHas('list', static fn(Builder $query): Builder => $query
                ->where('order_id', $order->getKey())
                ->where('is_included', true))
            ->get();
    }

    /** @return list<int> */
    private function itemIds(Order $order): array
    {
        /** @var list<int> */
        return OrderItem::query()
            ->whereHas('list', static fn(Builder $query): Builder => $query
                ->where('order_id', $order->getKey()))
            ->pluck('id')
            ->all();
    }

    private function covered(OrderItem $item): float
    {
        $covering = array_values(array_map(
            static fn(TemperingItemStatus $status): string => $status->value,
            array_filter(
                TemperingItemStatus::cases(),
                static fn(TemperingItemStatus $status): bool => $status->covers(),
            ),
        ));

        return (float) TemperingItem::query()
            ->where('order_item_id', $item->getKey())
            ->whereIn('status', $covering)
            ->sum('quantity');
    }

    private function enqueue(OrderItem $item, float $quantity): TemperingItem
    {
        /** @var TemperingItem */
        return TemperingItem::query()->create([
            'order_item_id' => (int) $item->getKey(),
            'status' => TemperingItemStatus::QUEUED->value,
            'quantity' => (string) $quantity,
        ]);
    }

    /**
     * Zmniejszenie ilości na zleceniu zdejmuje z kolejki nadmiar —
     * ale tylko z pozycji, które jeszcze nie pojechały.
     */
    private function trim(OrderItem $item, float $excess): void
    {
        DB::transaction(function () use ($item, $excess): void {
            $left = $excess;

            /** @var iterable<TemperingItem> $queued */
            $queued = TemperingItem::query()
                ->where('order_item_id', $item->getKey())
                ->where('status', TemperingItemStatus::QUEUED->value)
                ->whereNull('tempering_batch_id')
                ->orderByDesc('id')
                ->get();

            foreach ($queued as $position) {
                if ($left <= 0) {
                    return;
                }

                $quantity = (float) $position->quantity;

                if ($quantity <= $left) {
                    $left = round($left - $quantity, 3);
                    $position->delete();

                    continue;
                }

                $position->quantity = (string) round($quantity - $left, 3);
                $position->save();

                return;
            }
        });
    }
}
