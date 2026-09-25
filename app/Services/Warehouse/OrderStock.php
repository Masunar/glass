<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Enum\StockMovementType;
use Illuminate\Support\Facades\DB;

/**
 * Okucia zlecenia wobec magazynu.
 *
 * Rezerwacja i rozchód są **zdarzeniami powiązanymi ze statusem**, nie
 * osobną czynnością do zapamiętania: przejście do `ZLECENIE` rezerwuje,
 * do `PRODUKCJA` wydaje, do `ANULOWANE` zwalnia. W starym systemie stan
 * zdejmował człowiek albo nikt — stąd zlecenie 16492 ze statusem
 * „Gotowe" przy zerowym stanie wszystkich okuć.
 *
 * **Uzgadnianie, nie dokładanie.** Każda z tych metod doprowadza stan
 * rezerwacji do tego, co wynika ze zlecenia *teraz*, zamiast dopisywać
 * kolejną porcję. Dzięki temu powtórne wejście na ten sam status —
 * a to się zdarza po poprawce wyceny — nie rezerwuje drugi raz, tak
 * samo jak `ProductionPlan::sync()` nie zakłada drugiego kompletu zadań.
 */
final readonly class OrderStock
{
    public function __construct(
        private StockLedger $ledger = new StockLedger(),
    ) {
    }

    /**
     * Ile czego zlecenie potrzebuje.
     *
     * Liczą się wyłącznie listy wliczone do zlecenia. Wariant ofertowy,
     * którego klient nie wybrał, nie ma prawa blokować towaru.
     *
     * @return array<int, float> product_id => ilość
     */
    public function needs(Order $order): array
    {
        /** @var iterable<OrderItem> $items */
        $items = OrderItem::query()
            ->whereHas('list', static function ($query) use ($order): void {
                $query->where('order_id', $order->getKey())->where('is_included', true);
            })
            ->where('section', Section::FITTINGS->value)
            ->whereNotNull('product_id')
            ->get();

        $needs = [];

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            $needs[$productId] = ($needs[$productId] ?? 0.0) + (float) $item->quantity;
        }

        return $needs;
    }

    /**
     * Ile zlecenie ma już zarezerwowane — z rejestru, nie z pamięci.
     *
     * @return array<int, float>
     */
    public function reservedFor(Order $order): array
    {
        /** @var iterable<StockMovement> $movements */
        $movements = StockMovement::query()
            ->where('order_id', $order->getKey())
            ->whereIn('type', [
                StockMovementType::RESERVATION->value,
                StockMovementType::RELEASE->value,
            ])
            ->get();

        $reserved = [];

        foreach ($movements as $movement) {
            $productId = (int) $movement->product_id;
            $reserved[$productId] = ($reserved[$productId] ?? 0.0)
                + $movement->type->sign() * (float) $movement->quantity;
        }

        return $reserved;
    }

    /** Doprowadza rezerwacje do tego, czego zlecenie potrzebuje teraz. */
    public function reserve(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $needs = $this->needs($order);
            $reserved = $this->reservedFor($order);

            /** @var array<int, float> $all */
            $all = $needs + $reserved;

            foreach (array_keys($all) as $productId) {
                $difference = round(($needs[$productId] ?? 0.0) - ($reserved[$productId] ?? 0.0), 3);

                if ($difference === 0.0) {
                    continue;
                }

                $product = $this->product($productId);

                if ($product === null) {
                    continue;
                }

                $difference > 0
                    ? $this->ledger->reserve($product, $difference, orderId: (int) $order->getKey())
                    : $this->ledger->release($product, abs($difference), orderId: (int) $order->getKey());
            }
        });
    }

    /** Zwalnia wszystko, co zlecenie trzymało. */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($this->reservedFor($order) as $productId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $product = $this->product($productId);

                if ($product === null) {
                    continue;
                }

                $this->ledger->release($product, $quantity, orderId: (int) $order->getKey());
            }
        });
    }

    /**
     * Wydanie na produkcję: rezerwacja zamienia się w rozchód.
     *
     * Zwolnienie idzie przed wydaniem, żeby w żadnym momencie nie
     * wyglądało, że towar jest jednocześnie obiecany i wydany.
     */
    public function issue(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $this->release($order);

            foreach ($this->needs($order) as $productId => $quantity) {
                $product = $this->product($productId);

                if ($product === null) {
                    continue;
                }

                $this->ledger->issue($product, $quantity, orderId: (int) $order->getKey());
            }
        });
    }

    /**
     * Czego zabraknie, gdyby wydać teraz.
     *
     * Liczone od stanu **fizycznego**, bo wydanie zdejmuje z półki.
     * Rezerwacje innych zleceń tu nie wchodzą — to osobny konflikt
     * i osobna rozmowa, a zlecenie, które akurat weszło na halę, nie ma
     * przegrywać z obietnicą złożoną komuś innemu.
     *
     * @return list<array{product_id: int, name: string, needed: float, in_stock: float}>
     */
    public function shortages(Order $order): array
    {
        $rows = [];

        foreach ($this->needs($order) as $productId => $quantity) {
            $product = $this->product($productId);

            if ($product === null) {
                continue;
            }

            $inStock = (float) $this->ledger->level($product)->quantity;

            if ($inStock >= $quantity) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'name' => $product->name,
                'needed' => $quantity,
                'in_stock' => $inStock,
            ];
        }

        return $rows;
    }

    /**
     * Kompletność okuć zleceń — procent do kolumny „Okucia" na liście.
     *
     * **Ta sama reguła co `shortages()`**, tylko policzona dla całej
     * strony naraz: okucie jest „mamy", gdy stan fizyczny pokrywa to, czego
     * zlecenie potrzebuje. 100% znaczy dokładnie tyle, co brak blokady
     * „brakuje okuć na stanie" przy przejściu do produkcji — dwie liczby
     * o tym samym nie mogą się rozjechać.
     *
     * Zlecenie, któremu okucia już wydano (rozchód przy wejściu na
     * produkcję), ma komplet: towar zszedł z półki na to zlecenie, a stan
     * po wydaniu mówi już o innych zleceniach. Zlecenie bez okuć nie ma
     * wpisu — kreska, nie „100%" ani „0%".
     *
     * Stan jest wspólny: każde zlecenie porównane z całą półką, jak
     * w `shortages()`. Konflikt dwóch zleceń o ten sam towar to osobna
     * rozmowa (rezerwacje), nie ta kolumna.
     *
     * @param list<int> $orderIds
     * @return array<int, array{percent: int, short: int, products: int}>
     */
    public function completeness(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        /** @var iterable<\stdClass> $rows */
        $rows = OrderItem::query()
            ->toBase()
            ->join('order_lists', 'order_lists.id', '=', 'order_items.order_list_id')
            ->whereIn('order_lists.order_id', $orderIds)
            ->where('order_lists.is_included', true)
            ->where('order_items.section', Section::FITTINGS->value)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_lists.order_id', 'order_items.product_id')
            ->selectRaw('order_lists.order_id as order_id, order_items.product_id as product_id,'
                . ' SUM(order_items.quantity) as needed')
            ->get();

        $needs = [];

        foreach ($rows as $row) {
            $needs[(int) $row->order_id][(int) $row->product_id] = (float) $row->needed;
        }

        if ($needs === []) {
            return [];
        }

        /** @var list<int> $issued */
        $issued = StockMovement::query()
            ->whereIn('order_id', array_keys($needs))
            ->where('type', StockMovementType::ISSUE->value)
            ->distinct()
            ->pluck('order_id')
            ->map(static fn(mixed $id): int => (int) $id)
            ->all();

        $productIds = [];

        foreach ($needs as $products) {
            foreach (array_keys($products) as $productId) {
                $productIds[$productId] = true;
            }
        }

        $levels = $this->levelsFor(array_keys($productIds));
        $result = [];

        foreach ($needs as $orderId => $products) {
            $needed = 0.0;
            $covered = 0.0;
            $short = 0;

            foreach ($products as $productId => $quantity) {
                $have = in_array($orderId, $issued, true)
                    ? $quantity
                    : min($quantity, $levels[$productId] ?? 0.0);

                $needed += $quantity;
                $covered += $have;

                if ($have < $quantity) {
                    $short++;
                }
            }

            $result[$orderId] = [
                // W dol: 99% to jeszcze nie komplet.
                'percent' => $needed <= 0.0 ? 100 : (int) floor($covered / $needed * 100 + 1e-9),
                'short' => $short,
                'products' => count($products),
            ];
        }

        return $result;
    }

    /**
     * Stany fizyczne dla listy produktów — jednym zapytaniem.
     *
     * Ekran zlecenia pokazuje stan przy każdym okuciu, a pytanie
     * o każdy z osobna zamieniłoby otwarcie zakładki w kilkanaście
     * zapytań.
     *
     * @param list<int> $productIds
     * @return array<int, float>
     */
    public function levelsFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        /** @var iterable<StockLevel> $levels */
        $levels = StockLevel::query()
            ->whereIn('product_id', $productIds)
            ->get();

        $rows = [];

        foreach ($levels as $level) {
            $productId = (int) $level->product_id;
            $rows[$productId] = ($rows[$productId] ?? 0.0) + (float) $level->quantity;
        }

        return $rows;
    }

    private function product(int $productId): ?Product
    {
        /** @var Product|null */
        return Product::query()->find($productId);
    }
}
