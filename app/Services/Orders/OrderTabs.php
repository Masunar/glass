<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\AuditEntry;
use App\Models\OrderDrawing;

/**
 * Liczniki zakładek zlecenia.
 *
 * Każdy ekran zlecenia pokazuje ten sam pasek zakładek, więc każdy
 * potrzebuje tych samych liczb. Liczone zapytaniem, nie z wczytanych
 * relacji: ekran rysunków nie ma powodu wciągać wszystkich pozycji
 * tylko po to, żeby napisać „Formatki 3".
 */
final readonly class OrderTabs
{
    /**
     * @return array{panes: int, drawings: int, payments: int, log: int}
     */
    public function counts(Order $order): array
    {
        $id = (int) $order->getKey();

        return [
            'panes' => OrderItem::query()
                ->where('section', Section::GLASS->value)
                ->whereHas('list', static fn($query) => $query->where('order_id', $id))
                ->count(),
            'drawings' => OrderDrawing::query()->where('order_id', $id)->count(),
            'payments' => Payment::query()->where('order_id', $id)->count(),
            'log' => AuditEntry::query()
                ->where('auditable_type', Order::class)
                ->where('auditable_id', $id)
                ->count(),
        ];
    }
}
