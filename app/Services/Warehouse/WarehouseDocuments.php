<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Carbon\Carbon;
use App\Models\PurchaseOrder;
use App\Models\GlobalParameter;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\PurchaseOrderItem;

/**
 * Wydruki magazynu: zamówienie do dostawcy i lista kompletacji.
 *
 * Uwaga klienta (25.09), punkt 3b: wydruk zamówienia („zamówienie
 * z 25.09") do sprawdzenia kompletności dostawy — stąd pusta kolumna
 * „przyjęto" do odhaczenia długopisem. Punkt 3d: ta sama kartka dla
 * magazyniera przy kompletacji.
 *
 * Dane firmy z parametrów ogólnych, jak na ofercie. Brak nazwy firmy
 * zostawia nagłówek pusty — podstawienie czegokolwiek dałoby dokument,
 * który wygląda na kompletny.
 */
final readonly class WarehouseDocuments
{
    public function __construct(
        private PickingList $picking = new PickingList(),
    ) {
    }

    public function purchaseOrder(PurchaseOrder $order): string
    {
        $order->loadMissing(['supplier', 'items.product']);

        $items = [];

        /** @var iterable<PurchaseOrderItem> $rows */
        $rows = $order->items;

        foreach ($rows as $item) {
            $items[] = [
                'code' => $item->product?->code,
                'name' => $item->product?->name,
                'unit' => $item->product?->unit->value,
                'ordered' => $this->quantity((float) $item->quantity_ordered),
                'received' => $this->quantity((float) $item->quantity_received),
            ];
        }

        return Pdf::loadView('warehouse.purchase-order', [
            'seller' => $this->seller(),
            'number' => $order->number,
            'status' => $order->status->label(),
            'ordered_at' => $order->ordered_at?->format('d.m.Y'),
            'expected_at' => $order->expected_at?->format('d.m.Y'),
            'created_at' => $order->created_at->format('d.m.Y'),
            'note' => $order->note,
            'supplier' => [
                'name' => $order->supplier?->name,
                'contact' => $order->supplier?->contact_person,
                'phone' => $order->supplier?->phone,
                'email' => $order->supplier?->email,
            ],
            'items' => $items,
        ])->setPaper('a4')->output();
    }

    public function purchaseOrderFileName(PurchaseOrder $order): string
    {
        return sprintf('zamowienie-%d.pdf', $order->number);
    }

    public function picking(Carbon $from, Carbon $to, Carbon $today): string
    {
        $board = $this->picking->board($from, $to, $today);

        return Pdf::loadView('warehouse.picking', [
            'seller' => $this->seller(),
            'from' => $from->format('d.m.Y'),
            'to' => $to->format('d.m.Y'),
            'printed_at' => Carbon::now()->format('d.m.Y H:i'),
            'rows' => $board['rows'],
        ])->setPaper('a4')->output();
    }

    /** @return array{name: string|null, address: string|null, phone: string|null} */
    private function seller(): array
    {
        return [
            'name' => GlobalParameter::value('company_name'),
            'address' => GlobalParameter::value('company_address'),
            'phone' => GlobalParameter::value('company_phone'),
        ];
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
    }
}
