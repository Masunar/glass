<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ExtraDelivery;
use App\Models\StockMovement;
use App\Services\AuditTrail;
use App\Enum\StockMovementType;
use App\Services\NumberSequence;
use App\Models\ExtraDeliveryItem;
use App\Enum\ExtraDeliveryReason;
use App\Enum\ExtraDeliveryStatus;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Dostawy dodatkowe do zleceń — reklamacja, błędne okucie, domówienie.
 *
 * Uwaga klienta (25.09), punkt 3c; decyzja Marcina: **osobny dokument**,
 * nie pozycja zamówienia do dostawcy. Zamówienie uzupełnia półkę,
 * a dostawa dodatkowa przychodzi dla jednego zlecenia i ma powód.
 *
 * Przyjęcie zapisuje ruch przyjęcia z numerem zlecenia (`DD/numer`).
 * Gdy okucia tego zlecenia **już wydano** (zlecenie weszło na
 * produkcję), towar od razu wychodzi na to zlecenie drugim ruchem —
 * inaczej leżałby na stanie jako wolny, choć ma właściciela. Gdy nie
 * wydano, zostaje na stanie i pokrywa potrzebę zlecenia przy zwykłym
 * wydaniu na produkcję; wydanie go tutaj dałoby podwójny rozchód.
 */
final readonly class ExtraDeliveryService
{
    public const SEQUENCE = 'extra_deliveries';

    public function __construct(
        private StockLedger $ledger = new StockLedger(),
        private NumberSequence $numbers = new NumberSequence(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function create(array $input): array
    {
        $validator = Validator::make($input, [
            'order_id' => ['required', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'reason' => ['required', Rule::enum(ExtraDeliveryReason::class)],
            'expected_at' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
        ], [
            'reason.required' => 'Wskaż powód dostawy.',
            'items.required' => 'Dodaj co najmniej jedno okucie.',
            'items.*.quantity.min' => 'Ilość musi być większa od zera.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $order = Order::query()->find((int) $input['order_id']);

        if (!$order instanceof Order) {
            return ['errors' => ['order_id' => ['Nie ma takiego zlecenia.']], 'id' => null];
        }

        $supplierId = is_numeric($input['supplier_id'] ?? null) ? (int) $input['supplier_id'] : null;

        if ($supplierId !== null && !Supplier::query()->whereKey($supplierId)->exists()) {
            return ['errors' => ['supplier_id' => ['Nie ma takiego dostawcy.']], 'id' => null];
        }

        /** @var list<array{product_id: int|string, quantity: int|float|string}> $items */
        $items = $input['items'];
        $productIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) $row['product_id'],
            $items,
        )));

        $known = Product::query()
            ->whereIn('id', $productIds)
            ->where('section', Section::FITTINGS->value)
            ->count();

        if ($known !== count($productIds)) {
            return ['errors' => ['items' => ['Dostawa dodatkowa dotyczy okuć — część pozycji nie jest okuciem.']], 'id' => null];
        }

        $delivery = DB::transaction(function () use ($order, $supplierId, $input, $items): ExtraDelivery {
            /** @var ExtraDelivery $delivery */
            $delivery = ExtraDelivery::query()->create([
                'number' => $this->numbers->next(self::SEQUENCE),
                'order_id' => (int) $order->getKey(),
                'supplier_id' => $supplierId,
                'reason' => (string) $input['reason'],
                'status' => ExtraDeliveryStatus::EXPECTED->value,
                'expected_at' => is_string($input['expected_at'] ?? null) ? $input['expected_at'] : null,
                'note' => is_string($input['note'] ?? null) && trim($input['note']) !== '' ? trim($input['note']) : null,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $row) {
                ExtraDeliveryItem::query()->create([
                    'extra_delivery_id' => (int) $delivery->getKey(),
                    'product_id' => (int) $row['product_id'],
                    'quantity' => number_format((float) $row['quantity'], 3, '.', ''),
                ]);
            }

            return $delivery;
        });

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'dostawa dodatkowa',
                'before' => null,
                'after' => sprintf('DD/%d · %s', $delivery->number, $delivery->reason->label()),
            ]],
            'extra_delivery_created',
        );

        return ['errors' => [], 'id' => (int) $delivery->getKey()];
    }

    /**
     * Przyjęcie na magazyn — patrz opis klasy, kiedy towar od razu
     * wychodzi na zlecenie.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function receive(int $deliveryId, ?Carbon $receivedAt = null): array
    {
        /** @var ExtraDelivery $delivery */
        $delivery = ExtraDelivery::query()->with('items.product')->findOrFail($deliveryId);

        if ($delivery->status !== ExtraDeliveryStatus::EXPECTED) {
            return ['errors' => ['status' => ['Tę dostawę już ' . mb_strtolower($delivery->status->label()) . '.']]];
        }

        $orderId = $delivery->order_id;
        $document = 'DD/' . $delivery->number;
        $issued = StockMovement::query()
            ->where('order_id', $orderId)
            ->where('type', StockMovementType::ISSUE->value)
            ->exists();

        DB::transaction(function () use ($delivery, $orderId, $document, $issued, $receivedAt): void {
            foreach ($delivery->items as $item) {
                $product = $item->product;

                if (!$product instanceof Product) {
                    continue;
                }

                $note = $delivery->reason->label();

                $this->ledger->receive($product, (float) $item->quantity, document: $document, note: $note, orderId: $orderId);

                if ($issued) {
                    $this->ledger->issue($product, (float) $item->quantity, orderId: $orderId, note: $document);
                }
            }

            $delivery->status = ExtraDeliveryStatus::RECEIVED;
            $delivery->received_at = ($receivedAt ?? Carbon::today())->startOfDay();
            $delivery->received_by = Auth::id();
            $delivery->save();
        });

        $this->audit->write(
            Order::class,
            $orderId,
            [['field' => 'dostawa dodatkowa', 'before' => $document . ' oczekiwana', 'after' => $document . ' przyjęta']],
            'extra_delivery_received',
        );

        return ['errors' => []];
    }

    /** @return array{errors: array<string, list<string>>} */
    public function cancel(int $deliveryId): array
    {
        /** @var ExtraDelivery $delivery */
        $delivery = ExtraDelivery::query()->findOrFail($deliveryId);

        if ($delivery->status !== ExtraDeliveryStatus::EXPECTED) {
            return ['errors' => ['status' => ['Anulować można tylko dostawę, która jeszcze nie przyszła.']]];
        }

        $delivery->status = ExtraDeliveryStatus::CANCELLED;
        $delivery->save();

        $this->audit->write(
            Order::class,
            $delivery->order_id,
            [['field' => 'dostawa dodatkowa', 'before' => 'DD/' . $delivery->number . ' oczekiwana', 'after' => 'anulowana']],
            'extra_delivery_cancelled',
        );

        return ['errors' => []];
    }

    /**
     * Lista dostaw z filtrem stanu: `open` (oczekiwane), `received`,
     * `cancelled` albo `all`.
     *
     * @return array<string, mixed>
     */
    public function board(string $status = 'open'): array
    {
        $filter = match ($status) {
            'received' => [ExtraDeliveryStatus::RECEIVED->value],
            'cancelled' => [ExtraDeliveryStatus::CANCELLED->value],
            'all' => null,
            default => [ExtraDeliveryStatus::EXPECTED->value],
        };

        /** @var list<ExtraDelivery> $deliveries */
        $deliveries = ExtraDelivery::query()
            ->with(['order.contractor', 'supplier', 'items.product'])
            ->when($filter !== null, static fn($query) => $query->whereIn('status', $filter ?? []))
            ->orderByRaw('expected_at IS NULL')
            ->orderBy('expected_at')
            ->orderByDesc('number')
            ->limit(300)
            ->get()
            ->all();

        $counts = ExtraDelivery::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        /** @var list<Supplier> $suppliers */
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get()->all();

        return [
            'rows' => array_map(fn(ExtraDelivery $delivery): array => $this->row($delivery), $deliveries),
            'filters' => [
                ['code' => 'open', 'name' => 'Oczekiwane', 'count' => (int) ($counts[ExtraDeliveryStatus::EXPECTED->value] ?? 0)],
                ['code' => 'received', 'name' => 'Przyjęte', 'count' => (int) ($counts[ExtraDeliveryStatus::RECEIVED->value] ?? 0)],
                ['code' => 'cancelled', 'name' => 'Anulowane', 'count' => (int) ($counts[ExtraDeliveryStatus::CANCELLED->value] ?? 0)],
            ],
            'reasons' => array_map(
                static fn(ExtraDeliveryReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
                ExtraDeliveryReason::cases(),
            ),
            'suppliers' => array_map(
                static fn(Supplier $supplier): array => ['id' => (int) $supplier->getKey(), 'name' => $supplier->name],
                $suppliers,
            ),
        ];
    }

    /**
     * Dostawy dodatkowe zleceń — do listy kompletacji.
     *
     * @param list<int> $orderIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        /** @var list<ExtraDelivery> $deliveries */
        $deliveries = ExtraDelivery::query()
            ->with(['supplier', 'items.product'])
            ->whereIn('order_id', $orderIds)
            ->where('status', '!=', ExtraDeliveryStatus::CANCELLED->value)
            ->orderBy('number')
            ->get()
            ->all();

        $result = [];

        foreach ($deliveries as $delivery) {
            $result[$delivery->order_id][] = $this->row($delivery);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ExtraDelivery $delivery): array
    {
        $items = [];

        foreach ($delivery->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'code' => $item->product?->code,
                'name' => $item->product?->name,
                'quantity' => (float) $item->quantity,
            ];
        }

        $order = $delivery->relationLoaded('order') ? $delivery->order : null;

        return [
            'id' => (int) $delivery->getKey(),
            'number' => $delivery->number,
            'order_id' => $delivery->order_id,
            'order_number' => $order?->number,
            'contractor' => $order?->contractor?->short_name ?? $order?->contractor?->name,
            'supplier' => $delivery->supplier?->name,
            'reason' => $delivery->reason->value,
            'reason_label' => $delivery->reason->label(),
            'status' => $delivery->status->value,
            'status_label' => $delivery->status->label(),
            'expected_at' => $delivery->expected_at?->toDateString(),
            'received_at' => $delivery->received_at?->toDateString(),
            'note' => $delivery->note,
            'items' => $items,
        ];
    }
}
