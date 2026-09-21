<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Carbon\Carbon;
use RuntimeException;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockLevel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Services\NumberSequence;
use App\Models\PurchaseOrderItem;
use App\Enum\PurchaseOrderStatus;
use App\Models\PurchaseReceiptItem;
use App\Enum\PurchasePriceSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\Pricing\PurchasePriceLedger;

/**
 * Zamówienia do dostawców i przyjęcia towaru.
 *
 * Przyjęcie robi trzy rzeczy naraz i żadnej z nich nie da się pominąć:
 * dokumentuje, co przyszło; podnosi stan przez `StockLedger`, czyli
 * zdarzeniem, a nie korektą; i zapisuje nową cenę zakupu.
 *
 * **Cena sprzedaży się przy tym nie rusza** (M-08). Gdyby przeliczenie
 * szło samo, jedna dostawa droższa o trzy procent przestawiłaby ceny
 * na wszystkich otwartych ofertach, w tym już wysłanych do klienta.
 * Sygnał, że cennik rozjechał się z cenami zakupu, liczy `StockBoard`.
 *
 * **Nowa cena zakupu to cena z ostatniej dostawy** (M-16), nie średnia
 * ważona stanem. Średnia jest wierniejsza temu, co leży na półce, ale
 * jest liczbą, której nie ma w żadnym dokumencie.
 */
final readonly class PurchaseOrderService
{
    public const SEQUENCE = 'purchase_orders';

    public function __construct(
        private StockLedger $ledger = new StockLedger(),
        private NumberSequence $numbers = new NumberSequence(),
        private PurchasePriceLedger $prices = new PurchasePriceLedger(),
    ) {
    }

    public function draft(Supplier $supplier, ?Carbon $expectedAt = null, ?string $note = null): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return PurchaseOrder::query()->create([
            'number' => $this->numbers->next(self::SEQUENCE),
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::DRAFT->value,
            'expected_at' => $expectedAt,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Dołożenie pozycji. Powtórzony produkt podnosi ilość, zamiast
     * zakładać drugi wiersz — dwa wiersze tego samego towaru na jednym
     * zamówieniu to dokładnie ta pomyłka, którą stary system robił
     * w zapotrzebowaniu (`40-magazyn.md` §5).
     */
    public function addItem(
        PurchaseOrder $order,
        Product $product,
        float $quantity,
        ?string $unitNetPrice = null,
    ): PurchaseOrderItem {
        $this->assertEditable($order);

        if ($quantity <= 0) {
            throw new RuntimeException('Ilość na zamówieniu musi być większa od zera.');
        }

        /** @var PurchaseOrderItem|null $existing */
        $existing = PurchaseOrderItem::query()
            ->where('purchase_order_id', $order->getKey())
            ->where('product_id', $product->getKey())
            ->first();

        if ($existing !== null) {
            $existing->quantity_ordered = (string) round((float) $existing->quantity_ordered + $quantity, 3);

            if ($unitNetPrice !== null) {
                $existing->unit_net_price = $unitNetPrice;
            }

            $existing->save();

            return $existing->refresh();
        }

        /** @var PurchaseOrderItem */
        return PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity_ordered' => (string) $quantity,
            'quantity_received' => '0',
            'unit_net_price' => $unitNetPrice,
        ]);
    }

    /**
     * Szkice zamówień z sugestii zakupowych.
     *
     * Grupowane po dostawcy, bo zamówienie idzie do jednej firmy.
     * Produkt bez dostawcy nie trafia do żadnego szkicu i wraca
     * w `skipped` — cichy brak byłby tu najgorszy: człowiek zaznaczył
     * dziesięć pozycji, dostał osiem i nie ma jak zauważyć, których.
     *
     * Ilość bierze się z progów (`StockLevel::toOrder()`), więc liczy
     * się od stanu fizycznego, nie od dostępnego. Zamawiamy, żeby półka
     * wróciła do maksimum; liczenie od dostępnego pokrywałoby
     * rezerwacje dwa razy.
     *
     * @param list<int> $productIds
     * @return array{orders: list<PurchaseOrder>, skipped: list<array{product_id: int, name: string, reason: string}>}
     */
    public function fromSuggestions(array $productIds): array
    {
        /** @var iterable<StockLevel> $levels */
        $levels = StockLevel::query()
            ->with('product.supplier')
            ->whereIn('product_id', $productIds)
            ->get();

        /** @var array<int, list<array{product: Product, quantity: float}>> $bySupplier */
        $bySupplier = [];
        $skipped = [];

        foreach ($levels as $level) {
            $product = $level->product;
            $quantity = $level->toOrder();

            if ($quantity <= 0) {
                $skipped[] = [
                    'product_id' => (int) $product->getKey(),
                    'name' => $product->name,
                    'reason' => 'nie ma czego zamawiać — stan nie jest poniżej minimum',
                ];

                continue;
            }

            if ($product->supplier_id === null) {
                $skipped[] = [
                    'product_id' => (int) $product->getKey(),
                    'name' => $product->name,
                    'reason' => 'produkt nie ma przypisanego dostawcy',
                ];

                continue;
            }

            $bySupplier[$product->supplier_id][] = ['product' => $product, 'quantity' => $quantity];
        }

        $orders = [];

        foreach ($bySupplier as $supplierId => $entries) {
            /** @var Supplier|null $supplier */
            $supplier = Supplier::query()->find($supplierId);

            if ($supplier === null) {
                continue;
            }

            $order = $this->draft($supplier, note: 'Z sugestii zakupowych magazynu.');

            foreach ($entries as $entry) {
                $this->addItem(
                    $order,
                    $entry['product'],
                    $entry['quantity'],
                    $entry['product']->purchasePriceAt()?->net_price,
                );
            }

            $orders[] = $order->refresh();
        }

        return ['orders' => $orders, 'skipped' => $skipped];
    }

    public function removeItem(PurchaseOrder $order, PurchaseOrderItem $item): void
    {
        $this->assertEditable($order);

        $item->delete();
    }

    /** Wysłanie do dostawcy: od tej chwili pozycji się nie zmienia. */
    public function send(PurchaseOrder $order, ?Carbon $orderedAt = null): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('Wysłać można tylko szkic zamówienia.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Zamówienie bez pozycji nie ma czego zamawiać.');
        }

        $order->status = PurchaseOrderStatus::SENT;
        $order->ordered_at = $orderedAt ?? Carbon::today();
        $order->save();

        return $order->refresh();
    }

    /**
     * Anulowanie. Przyjęty towar zostaje na stanie — przyszedł
     * naprawdę i cofnięcie zamówienia tego nie odwraca.
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (!$order->status->isOpen()) {
            throw new RuntimeException('Zamówienie jest już zamknięte.');
        }

        $order->status = PurchaseOrderStatus::CANCELLED;
        $order->save();

        return $order->refresh();
    }

    /**
     * Przyjęcie towaru.
     *
     * @param list<array{item: PurchaseOrderItem, quantity: float, unit_net_price?: string|null}> $lines
     */
    public function receive(
        PurchaseOrder $order,
        array $lines,
        ?Carbon $receivedAt = null,
        ?string $document = null,
        ?string $note = null,
    ): PurchaseReceipt {
        if (!$order->status->isOpen()) {
            throw new RuntimeException('Do zamkniętego zamówienia nie przyjmuje się towaru.');
        }

        $lines = array_values(array_filter($lines, static fn(array $line): bool => $line['quantity'] > 0));

        if ($lines === []) {
            throw new RuntimeException('Przyjęcie bez pozycji nie dokumentuje niczego.');
        }

        $when = $receivedAt ?? Carbon::today();

        return DB::transaction(function () use ($order, $lines, $when, $document, $note): PurchaseReceipt {
            /** @var PurchaseReceipt $receipt */
            $receipt = PurchaseReceipt::query()->create([
                'purchase_order_id' => $order->getKey(),
                'received_at' => $when,
                'document' => $document,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $item = $line['item'];
                $quantity = round($line['quantity'], 3);
                $price = $line['unit_net_price'] ?? $item->unit_net_price;

                PurchaseReceiptItem::query()->create([
                    'purchase_receipt_id' => $receipt->getKey(),
                    'purchase_order_item_id' => $item->getKey(),
                    'quantity' => (string) $quantity,
                    'unit_net_price' => $price,
                ]);

                $item->quantity_received = (string) round((float) $item->quantity_received + $quantity, 3);
                $item->save();

                $product = $item->product;

                if ($product === null) {
                    throw new RuntimeException('Pozycja zamówienia bez produktu.');
                }

                $this->ledger->receive(
                    $product,
                    $quantity,
                    document: 'PZ',
                    note: sprintf('zamówienie %d', $order->number),
                );

                // Cena zakupu idzie z tej dostawy. Brak ceny nie kasuje
                // poprzedniej — przyjecie bez ceny na dokumencie nie jest
                // informacja, ze towar jest darmowy.
                if ($price !== null) {
                    $this->prices->set($product, $price, $when, PurchasePriceSource::DELIVERY);
                }
            }

            $this->settle($order);

            return $receipt->refresh();
        });
    }

    /**
     * Stan zamówienia wynika z ilości, a nie z osobnej decyzji.
     * Nadwyżka też zamyka: przyszło więcej, niż zamówiono, więc nie ma
     * na co czekać.
     */
    private function settle(PurchaseOrder $order): void
    {
        /** @var iterable<PurchaseOrderItem> $items */
        $items = $order->items()->get();

        $outstanding = 0.0;
        $received = 0.0;

        foreach ($items as $item) {
            $outstanding += $item->outstanding();
            $received += (float) $item->quantity_received;
        }

        $order->status = match (true) {
            $outstanding <= 0.0 => PurchaseOrderStatus::RECEIVED,
            $received > 0.0 => PurchaseOrderStatus::PARTIAL,
            default => $order->status,
        };

        $order->save();
    }

    private function assertEditable(PurchaseOrder $order): void
    {
        if (!$order->status->isEditable()) {
            throw new RuntimeException('Pozycje zmienia się tylko w szkicu zamówienia.');
        }
    }
}
