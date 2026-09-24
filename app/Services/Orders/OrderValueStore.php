<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Wartość zlecenia zapamiętana na zleceniu.
 *
 * **Liczy ją dalej wyłącznie `OrderValue`.** Ta klasa niczego nie liczy
 * po swojemu — zapisuje wynik, żeby saldo kontrahenta było jedną sumą,
 * a nie wczytaniem wszystkich jego otwartych zleceń z listami,
 * pozycjami i procesami. Symulacja na 10 000 zleceń: siedem sekund
 * przy każdym otwarciu listy, bo kolumna „co dalej" pyta o limit
 * kupiecki. Saldo liczone w SQL zostało odrzucone — byłoby drugą
 * definicją wartości obok `OrderValue`, z rabatami per sekcja, VAT per
 * lista i inwestycją powtórzonymi w zapytaniu.
 *
 * **Nieaktualna, a nie zła.** Każda zmiana, od której zależy wartość —
 * pozycja, proces, lista, rabat, typ faktury, inwestycja — oznacza
 * zlecenie jako nieaktualne (`value_stale`). Przeliczenie idzie przy
 * najbliższym odczycie albo hurtem przez `glass:order-values`. Zapisana
 * kwota, której nikt nie odświeżył, byłaby najgorszym możliwym
 * wynikiem: wyglądałaby dokładnie tak samo jak prawdziwa.
 */
final readonly class OrderValueStore
{
    private const CHUNK = 200;

    public function __construct(
        private OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * Przelicza nieaktualne zlecenia — wszystkie albo tylko wskazanych
     * kontrahentów.
     *
     * `$openOnly` pomija zlecenia w statusie końcowym — do salda nie
     * wchodzą, a archiwum to większość bazy.
     *
     * @param list<int>|null $contractorIds
     */
    public function refreshStale(?array $contractorIds = null, bool $openOnly = false): int
    {
        $refreshed = 0;

        Order::query()
            ->with(['lists.items.processes', 'discounts', 'invoiceType'])
            ->where('value_stale', true)
            ->when(
                $contractorIds !== null,
                static fn(Builder $builder): Builder => $builder->whereIn('contractor_id', $contractorIds ?? []),
            )
            ->when(
                $openOnly,
                static fn(Builder $builder): Builder => $builder->whereHas(
                    'status',
                    static fn(Builder $status): Builder => $status->where('is_final', false),
                ),
            )
            ->chunkById(self::CHUNK, function (Collection $orders) use (&$refreshed): void {
                /** @var Collection<int, Order> $orders */
                foreach ($orders as $order) {
                    $this->store($order);
                    $refreshed++;
                }
            });

        return $refreshed;
    }

    /**
     * Zapis wyniku `OrderValue` na zleceniu.
     *
     * Przez zapytanie, nie przez model: zapis modelu uruchomiłby
     * zdarzenia, które same oznaczają zlecenie jako nieaktualne.
     */
    public function store(Order $order): void
    {
        $totals = $this->value->totals($order);

        Order::query()->whereKey($order->getKey())->update([
            'value_net' => $totals->net,
            'value_gross' => $totals->gross,
            'value_stale' => false,
        ]);
    }

    public static function markStale(?int $orderId): void
    {
        if ($orderId === null || $orderId <= 0) {
            return;
        }

        Order::query()->whereKey($orderId)->update(['value_stale' => true]);
    }

    public static function markStaleByList(?int $listId): void
    {
        if ($listId === null) {
            return;
        }

        $orderId = OrderList::query()->whereKey($listId)->value('order_id');

        self::markStale(is_numeric($orderId) ? (int) $orderId : null);
    }

    public static function markStaleByItem(?int $itemId): void
    {
        if ($itemId === null) {
            return;
        }

        $listId = OrderItem::query()->whereKey($itemId)->value('order_list_id');

        self::markStaleByList(is_numeric($listId) ? (int) $listId : null);
    }
}
