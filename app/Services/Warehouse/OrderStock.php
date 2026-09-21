<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
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

    private function product(int $productId): ?Product
    {
        /** @var Product|null */
        return Product::query()->find($productId);
    }
}
