<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use App\Enum\Permission;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use App\Models\PurchaseOrderItem;
use Salvon\Controller\ApiController;
use App\Services\PriceListService;
use App\Services\Warehouse\StockBoard;
use App\Services\Warehouse\PurchaseOrderBoard;
use App\Services\Warehouse\PurchaseOrderService;

/**
 * Zamówienia do dostawców i przyjęcia towaru.
 */
class PurchaseOrderController extends ApiController
{
    public function __construct(
        private readonly PurchaseOrderBoard $board,
        private readonly PurchaseOrderService $service,
        private readonly StockBoard $stock,
        private readonly PriceListService $priceList,
    ) {
        $this->protect(
            ['index', 'show', 'drift'],
            Permission::WAREHOUSE->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['store', 'fromSuggestions', 'addItem', 'send', 'cancel', 'receive'],
            Permission::WAREHOUSE->value,
            SubPermission::UPDATE->value,
        );
        $this->protect(
            ['removeItem'],
            Permission::WAREHOUSE->value,
            SubPermission::DELETE->value,
        );
        // Ekran rozjazdu stoi w magazynie, ale przeliczenie zmienia
        // **ceny sprzedazy**. Zaopatrzeniowiec ma widziec rozjazd i nie
        // miec czym go przeliczyc — stad inne uprawnienie niz reszta
        // tego kontrolera.
        $this->protect(
            ['recalculate'],
            Permission::PRICE_LIST->value,
            SubPermission::UPDATE->value,
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->board(
            $request->query('status') === null ? null : (string) $request->query('status'),
        )));
    }

    public function show(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->card($this->order($order)),
        ));
    }

    /** Produkty, których cena zakupu wyprzedziła cennik sprzedaży. */
    public function drift(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->stock->priceDrift()));
    }

    /**
     * Przeliczenie cennika dla wskazanych produktów.
     *
     * Nie liczy niczego po swojemu — `PriceListService` przepuszcza
     * istniejące współczynniki przez aktualną cenę zakupu i zakłada
     * nową wersję cennika.
     */
    public function recalculate(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var list<int> $ids */
            $ids = array_values(array_unique(array_map(
                intval(...),
                (array) $request->input('product_ids', []),
            )));

            $result = $this->priceList->recalculate($ids);

            return $this->dataResponse([
                ...$result,
                'drift' => $this->stock->priceDrift(),
            ]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail((int) $request->input('supplier_id'));

            $expected = $request->input('expected_at');

            $order = $this->service->draft(
                $supplier,
                $expected === null || $expected === '' ? null : Carbon::parse((string) $expected),
                $request->input('note'),
            );

            return $this->dataResponse($this->board->card($order));
        });
    }

    /**
     * Szkice z zaznaczonych sugestii zakupowych.
     *
     * Odpowiedź niesie `skipped`, bo pominięta pozycja musi być widoczna:
     * człowiek zaznaczył dziesięć, a zamówienia objęły osiem.
     */
    public function fromSuggestions(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var list<int> $ids */
            $ids = array_map(intval(...), (array) $request->input('product_ids', []));

            $result = $this->service->fromSuggestions($ids);

            return $this->dataResponse([
                'orders' => array_map(fn($order): array => $this->board->card($order), $result['orders']),
                'skipped' => $result['skipped'],
            ]);
        });
    }

    public function addItem(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $model = $this->order($order);

            /** @var Product $product */
            $product = Product::query()->findOrFail((int) $request->input('product_id'));

            $price = $request->input('unit_net_price');

            $this->service->addItem(
                $model,
                $product,
                (float) $request->input('quantity', 0),
                $price === null || $price === '' ? null : (string) $price,
            );

            return $this->dataResponse($this->board->card($model->refresh()));
        });
    }

    public function removeItem(int $order, int $item): JsonResponse
    {
        return $this->secure(function () use ($order, $item): JsonResponse {
            $model = $this->order($order);

            /** @var PurchaseOrderItem $line */
            $line = PurchaseOrderItem::query()
                ->where('purchase_order_id', $model->getKey())
                ->findOrFail($item);

            $this->service->removeItem($model, $line);

            return $this->dataResponse($this->board->card($model->refresh()));
        });
    }

    public function send(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->card($this->service->send($this->order($order))),
        ));
    }

    public function cancel(int $order): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->card($this->service->cancel($this->order($order))),
        ));
    }

    /**
     * Przyjęcie towaru: podnosi stan i zapisuje nową cenę zakupu.
     * Cen sprzedaży nie rusza — patrz `PurchaseOrderService`.
     */
    public function receive(Request $request, int $order): JsonResponse
    {
        return $this->secure(function () use ($request, $order): JsonResponse {
            $model = $this->order($order);

            $lines = [];

            /** @var array<int, array<string, mixed>> $input */
            $input = (array) $request->input('lines', []);

            foreach ($input as $line) {
                /** @var PurchaseOrderItem|null $item */
                $item = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $model->getKey())
                    ->find((int) ($line['item_id'] ?? 0));

                if ($item === null) {
                    continue;
                }

                $price = $line['unit_net_price'] ?? null;

                $lines[] = [
                    'item' => $item,
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'unit_net_price' => $price === null || $price === '' ? null : (string) $price,
                ];
            }

            $receivedAt = $request->input('received_at');

            $this->service->receive(
                $model,
                $lines,
                $receivedAt === null || $receivedAt === '' ? null : Carbon::parse((string) $receivedAt),
                $request->input('document'),
                $request->input('note'),
            );

            return $this->dataResponse($this->board->card($model->refresh()));
        });
    }

    private function order(int $id): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return PurchaseOrder::query()->findOrFail($id);
    }
}
