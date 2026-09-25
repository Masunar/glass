<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enum\Section;
use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use App\Models\StockMovement;
use App\Services\AuditTrail;
use App\Enum\StockMovementType;
use App\Services\Orders\OrderOwnerService;
use Illuminate\Support\Facades\Auth;

/**
 * Lista kompletacji okuć dla magazyniera.
 *
 * Uwaga klienta (25.09), punkt 3d: „montaż 25.09 → przygotować na
 * 25.09". Decyzje Marcina: datą jest **obowiązujący termin zlecenia**
 * (przesunięty, a bez niego termin klienta) — modułu montaży jeszcze
 * nie ma; magazynier odhacza zlecenie jako przygotowane, z tym kto
 * i kiedy.
 *
 * Na liście są zlecenia w realizacji (Zlecenie, Produkcja, Gotowe)
 * z okuciami na wliczonych listach:
 *  - z terminem w wybranym zakresie;
 *  - zaległe i jeszcze nieprzygotowane — przeoczone nie znika z listy
 *    tylko dlatego, że jego dzień minął;
 *  - bez terminu i nieprzygotowane — osobna grupa na końcu.
 *
 * Stan okucia liczy się tak jak kolumna „Okucia" (`OrderStock`):
 * wydane, jeśli zlecenie weszło już na produkcję, inaczej porównanie
 * z półką.
 */
final readonly class PickingList
{
    /** Statusy, w których okucia są do przygotowania — po kodzie. */
    public const STATUSES = ['ZLECENIE', 'PRODUKCJA', 'GOTOWE'];

    private const LIMIT = 500;

    public function __construct(
        private OrderStock $stock = new OrderStock(),
        private ExtraDeliveryService $extra = new ExtraDeliveryService(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(CarbonInterface $from, CarbonInterface $to, CarbonInterface $today): array
    {
        $start = $from->copy()->startOfDay()->toDateString();
        // Gorna granica wylacznie: kolumna daty w SQLite niesie godzine
        // („2026-10-07 00:00:00"), a ta jako tekst jest wieksza od
        // „2026-10-07" — BETWEEN zgubilby ostatni dzien zakresu.
        $end = $to->copy()->startOfDay()->addDay()->toDateString();
        $deadline = 'COALESCE(orders.shifted_deadline, orders.client_deadline)';

        /** @var list<Order> $orders */
        $orders = Order::query()
            ->with(['contractor', 'status', 'fittingsPreparer'])
            ->whereHas('status', static fn($query) => $query->whereIn('code', self::STATUSES))
            ->whereExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->join('order_lists', 'order_lists.id', '=', 'order_items.order_list_id')
                    ->whereColumn('order_lists.order_id', 'orders.id')
                    ->where('order_lists.is_included', true)
                    ->where('order_items.section', Section::FITTINGS->value)
                    ->whereNotNull('order_items.product_id');
            })
            ->where(static function ($query) use ($deadline, $start, $end): void {
                $query->where(static function ($inRange) use ($deadline, $start, $end): void {
                    $inRange->whereRaw($deadline . ' >= ?', [$start])->whereRaw($deadline . ' < ?', [$end]);
                })->orWhere(static function ($late) use ($deadline, $start): void {
                    $late->whereRaw($deadline . ' < ?', [$start])->whereNull('orders.fittings_prepared_at');
                })->orWhere(static function ($none) use ($deadline): void {
                    $none->whereRaw($deadline . ' IS NULL')->whereNull('orders.fittings_prepared_at');
                });
            })
            ->orderByRaw($deadline . ' IS NULL')
            ->orderByRaw($deadline)
            ->orderBy('number')
            ->limit(self::LIMIT)
            ->get()
            ->all();

        $ids = array_map(static fn(Order $order): int => (int) $order->getKey(), $orders);
        $fittings = $this->fittings($ids);
        $extra = $this->extra->forOrders($ids);
        $day = $today->copy()->startOfDay()->toDateString();

        $rows = [];
        $prepared = 0;
        $short = 0;

        foreach ($orders as $order) {
            $id = (int) $order->getKey();
            $due = ($order->shifted_deadline ?? $order->client_deadline)?->toDateString();
            $lines = $fittings[$id] ?? [];
            $missing = count(array_filter($lines, static fn(array $line): bool => $line['state'] === 'short'));

            $prepared += $order->fittings_prepared_at !== null ? 1 : 0;
            $short += $missing > 0 ? 1 : 0;

            $rows[] = [
                'id' => $id,
                'number' => $order->number,
                'contractor' => $order->contractor->short_name ?? $order->contractor?->name,
                'status' => $order->status?->name,
                'delivery_method' => $order->delivery_method->value,
                'deadline' => $due,
                'is_late' => $due !== null && $due < $day,
                'prepared' => $order->fittings_prepared_at === null ? null : [
                    'at' => $order->fittings_prepared_at->format('Y-m-d H:i'),
                    'by' => $order->fittingsPreparer === null
                        ? null
                        : OrderOwnerService::name($order->fittingsPreparer),
                ],
                'missing' => $missing,
                'fittings' => $lines,
                'extra' => $extra[$id] ?? [],
            ];
        }

        // W obrebie dnia przygotowane schodza na dol — do zrobienia jest
        // to, co na gorze.
        usort($rows, static function (array $a, array $b): int {
            return [$a['deadline'] === null, $a['deadline'], $a['prepared'] !== null, $a['number']]
                <=> [$b['deadline'] === null, $b['deadline'], $b['prepared'] !== null, $b['number']];
        });

        return [
            'from' => $start,
            'to' => $to->copy()->startOfDay()->toDateString(),
            'rows' => $rows,
            'summary' => [
                'orders' => count($rows),
                'prepared' => $prepared,
                'short' => $short,
            ],
        ];
    }

    /**
     * Odhaczenie „przygotowane" albo jego cofnięcie.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function mark(int $orderId, bool $prepared): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        if (($order->fittings_prepared_at !== null) === $prepared) {
            return ['errors' => []];
        }

        $order->fittings_prepared_at = $prepared ? now() : null;
        $order->fittings_prepared_by = $prepared ? Auth::id() : null;
        $order->save();

        $this->audit->write(
            Order::class,
            $orderId,
            [['field' => 'okucia', 'before' => $prepared ? null : 'przygotowane', 'after' => $prepared ? 'przygotowane' : null]],
            $prepared ? 'fittings_prepared' : 'fittings_unprepared',
        );

        return ['errors' => []];
    }

    /**
     * Zmiana okuć na zleceniu unieważnia „przygotowane" — magazynier
     * spakował komplet, którego na zleceniu już nie ma. Woła to model
     * pozycji, więc działa przy każdej drodze zmiany.
     */
    public static function invalidateByList(int $listId): void
    {
        $orderId = OrderList::query()->whereKey($listId)->value('order_id');

        if (!is_numeric($orderId)) {
            return;
        }

        Order::query()
            ->whereKey((int) $orderId)
            ->whereNotNull('fittings_prepared_at')
            ->update(['fittings_prepared_at' => null, 'fittings_prepared_by' => null]);
    }

    /**
     * Okucia zleceń ze stanem każdej pozycji.
     *
     * @param list<int> $orderIds
     * @return array<int, list<array{product_id: int, code: string|null, name: string, quantity: float, in_stock: float, state: string}>>
     */
    private function fittings(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        /** @var iterable<\stdClass> $rows */
        $rows = OrderItem::query()
            ->toBase()
            ->join('order_lists', 'order_lists.id', '=', 'order_items.order_list_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_lists.order_id', $orderIds)
            ->where('order_lists.is_included', true)
            ->where('order_items.section', Section::FITTINGS->value)
            ->groupBy('order_lists.order_id', 'order_items.product_id', 'products.code', 'products.name')
            ->selectRaw('order_lists.order_id as order_id, order_items.product_id as product_id,'
                . ' products.code as code, products.name as name, SUM(order_items.quantity) as quantity')
            ->orderBy('products.name')
            ->get();

        /** @var list<int> $issued */
        $issued = StockMovement::query()
            ->whereIn('order_id', $orderIds)
            ->where('type', StockMovementType::ISSUE->value)
            ->distinct()
            ->pluck('order_id')
            ->map(static fn(mixed $id): int => (int) $id)
            ->all();

        $lines = [];
        $productIds = [];

        foreach ($rows as $row) {
            $productIds[(int) $row->product_id] = true;
            $lines[] = $row;
        }

        $levels = $this->stock->levelsFor(array_keys($productIds));
        $result = [];

        foreach ($lines as $row) {
            $orderId = (int) $row->order_id;
            $productId = (int) $row->product_id;
            $quantity = (float) $row->quantity;
            $inStock = $levels[$productId] ?? 0.0;

            $result[$orderId][] = [
                'product_id' => $productId,
                'code' => $row->code === null ? null : (string) $row->code,
                'name' => (string) $row->name,
                'quantity' => $quantity,
                'in_stock' => $inStock,
                'state' => in_array($orderId, $issued, true)
                    ? 'issued'
                    : ($inStock >= $quantity ? 'in_stock' : 'short'),
            ];
        }

        return $result;
    }
}
