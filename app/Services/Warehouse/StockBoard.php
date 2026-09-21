<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ekran magazynu: stany, progi i zapotrzebowanie zleceń.
 *
 * Na razie same okucia — to jedyna sekcja, która ma stany. Ramy, Inne
 * i pozostałe zakładki starego systemu byłyby dziś puste, a pusta
 * zakładka uczy ludzi, że nic tam nie ma, i przestają ją otwierać.
 * Model niczego nie ogranicza: `stock_levels` wiąże się z dowolnym
 * produktem, więc dołożenie sekcji to zmiana filtru, nie schematu.
 */
final readonly class StockBoard
{
    public function __construct(
        private OrderStock $orders = new OrderStock(),
    ) {
    }

    /**
     * Stany z progami i sugestią zakupową.
     *
     * @return array<string, mixed>
     */
    public function levels(?string $query = null, bool $shortagesOnly = false): array
    {
        /** @var iterable<StockLevel> $levels */
        $levels = StockLevel::query()
            ->with(['product.fitting', 'product.group'])
            ->whereHas('product', static function (Builder $builder) use ($query): void {
                $builder
                    ->where('section', Section::FITTINGS->value)
                    ->where('is_active', true)
                    ->when(
                        $query !== null && mb_strlen($query) >= 3,
                        static fn(Builder $inner): Builder => $inner->where(
                            static fn(Builder $where): Builder => $where
                                ->where('name', 'like', '%' . $query . '%')
                                ->orWhere('code', 'like', '%' . $query . '%'),
                        ),
                    );
            })
            ->get();

        $rows = [];
        $toOrder = 0;

        foreach ($levels as $level) {
            // Bez `instanceof`: `stock_levels.product_id` jest NOT NULL
            // z kaskadowym kasowaniem, wiec wiersz bez produktu nie
            // istnieje. Larastan czyta to z migracji i ma racje.
            $product = $level->product;
            $suggested = $level->toOrder();

            if ($suggested > 0) {
                $toOrder++;
            }

            if ($shortagesOnly && $suggested <= 0) {
                continue;
            }

            $rows[] = [
                'product_id' => (int) $product->getKey(),
                'code' => $product->code,
                'name' => $product->name,
                'group' => $product->group?->name,
                'finish' => $product->fitting?->finish,
                'quantity' => (float) $level->quantity,
                'reserved' => (float) $level->reserved,
                'available' => $level->available(),
                'min' => (float) $level->min_quantity,
                'max' => (float) $level->max_quantity,
                // Kolumna, ktora w starym systemie nazywala sie
                // „Produkcja" i nie miala z produkcja nic wspolnego.
                'to_order' => $suggested,
                'is_made_to_order' => (bool) $product->is_made_to_order,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$b['to_order'], $a['name']]
            <=> [$a['to_order'], $b['name']]);

        return [
            'rows' => $rows,
            'summary' => [
                'shown' => count($rows),
                'to_order' => $toOrder,
            ],
        ];
    }

    /**
     * „Okucia w zamówieniach": czego potrzebują zlecenia, a czego nie ma.
     *
     * **Jeden wiersz na zlecenie i produkt.** Stary ekran pokazywał
     * pozycje po kilka razy — `40-magazyn.md` §5 stawia hipotezę, że
     * złączenie mnożyło je przez liczbę list na zleceniu. Tu sumujemy
     * po produkcie, zanim cokolwiek trafi na ekran, bo zawyżone
     * zapotrzebowanie to zamówienie większe niż potrzeba.
     *
     * @return array<string, mixed>
     */
    public function demand(): array
    {
        /** @var iterable<Order> $orders */
        $orders = Order::query()
            ->with(['status', 'contractor'])
            ->whereHas('status', static fn(Builder $builder): Builder => $builder->where('is_final', false))
            ->whereHas('lists.items', static fn(Builder $builder): Builder => $builder
                ->where('section', Section::FITTINGS->value))
            ->orderBy('number')
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $needs = $this->orders->needs($order);
            $levels = $this->orders->levelsFor(array_keys($needs));

            foreach ($needs as $productId => $quantity) {
                /** @var Product|null $product */
                $product = Product::query()->with('fitting')->find($productId);

                if ($product === null) {
                    continue;
                }

                $inStock = $levels[$productId] ?? 0.0;

                $rows[] = [
                    'order_id' => (int) $order->getKey(),
                    'order_number' => (int) $order->number,
                    'contractor' => $order->contractor?->displayName(),
                    'status' => $order->status?->name,
                    'product_id' => $productId,
                    'code' => $product->code,
                    'name' => $product->name,
                    'finish' => $product->fitting?->finish,
                    'needed' => $quantity,
                    'in_stock' => $inStock,
                    'missing' => max(0.0, round($quantity - $inStock, 3)),
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int => [$b['missing'], $a['order_number']]
            <=> [$a['missing'], $b['order_number']]);

        return [
            'rows' => $rows,
            'summary' => [
                'shown' => count($rows),
                'missing' => count(array_filter($rows, static fn(array $row): bool => $row['missing'] > 0)),
            ],
        ];
    }
}
