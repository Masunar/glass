<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;

/**
 * Szacowany czas wykonania zlecenia.
 *
 * Liczony wyłącznie z dni wpisanych przy etapach — a te biorą się ze
 * słownika procesów (cięcie 2, szlif 3, faza 6, CNC 12) i człowiek może
 * je przy pozycji nadpisać. To **jedyna** liczba dni, jaką w tym
 * systemie wolno podać: zmierzonych czasów operacji wciąż nie ma, więc
 * wszystko poza sumą tego, co ktoś wpisał, byłoby zmyślone.
 *
 * **Formatki idą przez halę równolegle**, więc zlecenie trwa tyle, ile
 * najdłuższa z nich, a nie tyle, ile wszystkie po kolei. Pięćdziesiąt
 * formatek po pięć dni to pięć dni, nie dwieście pięćdziesiąt.
 *
 * Daty wykonania z tego nie wyprowadzamy. Dni mówią, ile pracy jest
 * w środku, ale nie mówią, kiedy hala ją zacznie — a bez tego data
 * byłaby obietnicą bez pokrycia. Stary system też ją zostawiał pustą.
 */
final readonly class OrderSchedule
{
    /**
     * Dni najdłuższej formatki. `null`, gdy nie ma z czego liczyć —
     * to nie to samo co zero.
     */
    public function days(Order $order): ?int
    {
        $order->loadMissing(['lists.items.processes']);

        $longest = null;

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            // Alternatywy nie wchodza do terminu z tego samego powodu,
            // z ktorego nie wchodza do kwoty: nie sa tym, co klient
            // zamowil.
            if (!$list->is_included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $days = 0;
                $counted = false;

                foreach ($item->processes as $entry) {
                    if ($entry->days === null) {
                        continue;
                    }

                    $days += (int) $entry->days;
                    $counted = true;
                }

                if ($counted && ($longest === null || $days > $longest)) {
                    $longest = $days;
                }
            }
        }

        return $longest;
    }
}
