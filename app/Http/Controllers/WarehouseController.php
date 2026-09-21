<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Warehouse\StockBoard;
use App\Services\Warehouse\StockLedger;
use App\Models\Product;

/**
 * Magazyn: stany, progi i zapotrzebowanie zleceń.
 *
 * Osobny kontroler i osobne uprawnienie, bo to ekran dla zaopatrzenia,
 * nie dla handlowca — tak samo jak kolejka hali.
 */
class WarehouseController extends ApiController
{
    public function __construct(
        private readonly StockBoard $board,
        private readonly StockLedger $ledger,
    ) {
        $this->protect(
            ['levels', 'demand'],
            Permission::WAREHOUSE->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['thresholds', 'count'],
            Permission::WAREHOUSE->value,
            SubPermission::UPDATE->value,
        );
    }

    public function levels(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->levels(
            $request->query('q') === null ? null : (string) $request->query('q'),
            $request->boolean('shortages'),
        )));
    }

    public function demand(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->demand()));
    }

    /** Progi zamówienia to decyzja człowieka, więc ustawia się je wprost. */
    public function thresholds(Request $request, int $product): JsonResponse
    {
        return $this->secure(function () use ($request, $product): JsonResponse {
            /** @var Product $item */
            $item = Product::query()->findOrFail($product);

            $level = $this->ledger->thresholds(
                $item,
                (float) $request->input('min', 0),
                (float) $request->input('max', 0),
            );

            return $this->dataResponse(['to_order' => $level->toOrder()]);
        });
    }

    /**
     * Inwentaryzacja. Zapisuje różnicę jako ruch — stanu nie da się
     * nadpisać, bo wtedy nie dałoby się odtworzyć, co się z nim stało.
     */
    public function count(Request $request, int $product): JsonResponse
    {
        return $this->secure(function () use ($request, $product): JsonResponse {
            /** @var Product $item */
            $item = Product::query()->findOrFail($product);

            $level = $this->ledger->count(
                $item,
                (float) $request->input('counted', 0),
                note: $request->input('note'),
            );

            return $this->dataResponse(['quantity' => (float) $level->quantity]);
        });
    }
}
