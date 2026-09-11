<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Process;
use App\Enum\AddressKind;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\AuditEntry;
use App\Models\ContractorAddress;

/**
 * Karta zlecenia — jedno miejsce decyzji.
 *
 * Ekran odpowiada na cztery pytania w tej kolejności: ile to jest warte,
 * na kiedy, czy klient ma jeszcze limit i co można z tym zrobić teraz.
 * Reszta — pozycje, formatki, komentarze — jest uzasadnieniem tych
 * czterech odpowiedzi, nie osobnym tematem.
 *
 * **Czego tu nie ma i dlaczego.** Projekt przewiduje pasek „Do zapłaty"
 * z procentem wpłat i ścieżkę produkcji ze stanem etapów. Modułu wpłat
 * i ewidencji produkcji jeszcze nie ma, więc zamiast „0 % zapłacone"
 * (co jest zdaniem fałszywym, nie brakiem danych) karta pokazuje
 * wartość zlecenia, a przy ścieżce mówi wprost, że wykonania nikt
 * jeszcze nie odnotowuje.
 */
final readonly class OrderCard
{
    public function __construct(
        private OrderNextStep $nextStep = new OrderNextStep(),
        private OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $orderId, ?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::today())->startOfDay();

        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor.addresses',
                'contractor.primaryContact',
                'status',
                'pickupLocation',
                'creator',
                'invoiceType',
                'discounts',
                'lists.items.pane',
                'lists.items.processes.process',
            ])
            ->findOrFail($orderId);

        $steps = $this->nextStep->forOrder($order);
        $totals = $this->value->totals($order);

        return [
            'order' => $this->header($order, $day),
            'money' => $totals->toArray(),
            'credit' => $this->credit($order, (float) $totals->net, $totals->vatRate),
            'steps' => array_map(static fn($step): array => $step->toArray(), $steps),
            'path' => $this->path($order),
            'lists' => $this->lists($order),
            'history' => $this->history($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function header(Order $order, Carbon $day): array
    {
        $contractor = $order->contractor;
        // Adresy są już wczytane, więc szukamy w kolekcji zamiast
        // dokładać zapytanie na każdą kartę.
        $address = $contractor?->addresses->firstWhere('kind', AddressKind::REGISTERED);
        $deadline = $order->effectiveDeadline();

        return [
            'id' => (int) $order->getKey(),
            'number' => (int) $order->number,
            'created_at' => $order->getRawOriginal('created_at'),
            'created_by' => $this->personName($order),
            'status' => $order->status?->name,
            'status_code' => $order->status?->code,
            'is_final' => (bool) ($order->status->is_final ?? false),
            'is_on_hold' => (bool) $order->is_on_hold,
            'hold_reason' => $order->hold_reason,
            'has_open_claim' => (bool) $order->has_open_claim,
            'contractor' => $contractor === null ? null : [
                'id' => (int) $contractor->getKey(),
                'name' => $contractor->name,
                'display_name' => $contractor->displayName(),
                'tax_id' => $contractor->tax_id,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'address' => $address === null ? null : $this->addressLine($address),
                'city' => $address === null
                    ? null
                    : trim(($address->postal_code ?? '') . ' ' . ($address->city ?? '')),
                'contact' => $contractor->primaryContact?->fullName(),
            ],
            'delivery' => [
                'method' => $order->delivery_method->value,
                'place' => $order->pickupLocation?->name,
                'address' => $order->delivery_address,
                'contact' => $order->delivery_contact,
            ],
            'invoice' => [
                'type' => $order->invoiceType?->name,
                'vat_rate' => $order->invoiceType?->vat_rate,
                'buyer_name' => $order->buyer_name,
                'buyer_tax_id' => $order->buyer_tax_id,
                'buyer_address' => $order->buyer_address,
                'accounting_note' => $order->accounting_note,
            ],
            'deadline' => [
                'client' => $order->client_deadline?->toDateString(),
                'production' => $order->production_deadline?->toDateString(),
                'shifted' => $order->shifted_deadline?->toDateString(),
                'shift_reason' => $order->shift_reason,
                'effective' => $deadline?->toDateString(),
                'days_left' => $deadline === null ? null : (int) $day->diffInDays($deadline, false),
            ],
            // Cztery komentarze to nie powtórzenie: treść dla montażysty
            // nie może trafić na ofertę do klienta.
            'comments' => [
                'short' => $order->short_note,
                'production' => $order->production_comment,
                'installer' => $order->installer_comment,
                'offer' => $order->offer_comment,
            ],
        ];
    }

    /**
     * Limit kupiecki bez modułu wpłat da się zestawić tylko z tym jednym
     * zleceniem. Ile klient jest winien z pozostałych — nie wiadomo,
     * i ekran mówi to wprost zamiast pokazywać zaniżone wykorzystanie.
     *
     * @return array<string, mixed>|null
     */
    private function credit(Order $order, float $net, ?int $vatRate): ?array
    {
        $contractor = $order->contractor;

        if ($contractor === null) {
            return null;
        }

        $limit = (float) $contractor->credit_limit;
        $value = $vatRate === null ? $net : $net * (100 + $vatRate) / 100;

        return [
            'limit' => $this->amount($limit),
            'payment_days' => $contractor->payment_days,
            'order_value' => $this->amount($value),
            'exceeds_by' => $value > $limit ? $this->amount($value - $limit) : null,
            'is_gross' => $vatRate !== null,
        ];
    }

    /**
     * Ścieżka produkcji zbudowana z procesów faktycznie przypisanych do
     * pozycji, w kolejności ze słownika procesów. Stanu wykonania nie
     * pokazujemy, bo nikt go jeszcze nie zapisuje.
     *
     * @return list<array<string, mixed>>
     */
    private function path(Order $order): array
    {
        /** @var array<int, array<string, mixed>> $steps */
        $steps = [];

        foreach ($this->includedLists($order) as $list) {
            foreach ($list->items as $item) {
                foreach ($item->processes as $entry) {
                    $process = $entry->process;

                    if (!$process instanceof Process) {
                        continue;
                    }

                    $id = (int) $process->getKey();

                    $steps[$id] ??= [
                        'code' => $process->code,
                        'name' => $process->name,
                        'order' => $process->default_order,
                        'is_subcontracted' => (bool) $process->is_subcontracted,
                        'items' => 0,
                        'amount' => 0.0,
                    ];

                    $steps[$id]['items'] = (int) $steps[$id]['items'] + 1;
                    $steps[$id]['amount'] = (float) $steps[$id]['amount'] + (float) $entry->amount;
                }
            }
        }

        usort($steps, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(
            fn(array $step): array => [
                'code' => $step['code'],
                'name' => $step['name'],
                'is_subcontracted' => $step['is_subcontracted'],
                'items' => $step['items'],
                'amount' => $this->amount((float) $step['amount']),
            ],
            $steps,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lists(Order $order): array
    {
        $rows = [];

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            $items = [];
            $net = 0.0;

            /** @var OrderItem $item */
            foreach ($list->items->sortBy('position') as $item) {
                $pane = $item->pane;
                $processes = [];
                $processAmount = 0.0;

                foreach ($item->processes as $entry) {
                    $processes[] = $entry->process->name ?? '—';
                    $processAmount += (float) $entry->amount;
                }

                $amount = (float) $item->amount + $processAmount;
                $net += $amount;

                $items[] = [
                    'id' => (int) $item->getKey(),
                    'section' => $item->section->value,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_net_price' => $item->unit_net_price,
                    'amount' => $this->amount($amount),
                    'processes' => $processes,
                    'pane' => $pane === null ? null : [
                        'width_mm' => $pane->width_mm,
                        'height_mm' => $pane->height_mm,
                        'is_irregular_shape' => (bool) $pane->is_irregular_shape,
                        'is_tempered' => (bool) $pane->is_tempered,
                        'needs_mark' => (bool) $pane->needs_mark,
                    ],
                ];
            }

            $rows[] = [
                'id' => (int) $list->getKey(),
                'number' => (int) $list->number,
                'name' => $list->name,
                'role' => $list->role->value,
                'is_included' => (bool) $list->is_included,
                'is_on_hold' => (bool) $list->is_on_hold,
                'comment' => $list->comment,
                'net' => $this->amount($net),
                'items' => $items,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Order $order): array
    {
        /** @var iterable<AuditEntry> $entries */
        $entries = AuditEntry::query()
            ->with('user')
            ->where('auditable_type', Order::class)
            ->where('auditable_id', (int) $order->getKey())
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $rows = [];

        foreach ($entries as $entry) {
            $user = $entry->user;

            $rows[] = [
                'at' => $entry->created_at->format('d.m.Y H:i'),
                'event' => $entry->event,
                'user' => $user === null
                    ? null
                    : trim((string) $user->first_name . ' ' . (string) $user->last_name),
                'changes' => $entry->changes ?? [],
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<OrderList>
     */
    private function includedLists(Order $order): iterable
    {
        foreach ($order->lists as $list) {
            if ($list->is_included) {
                yield $list;
            }
        }
    }

    private function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function personName(Order $order): ?string
    {
        $user = $order->creator;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }

    private function addressLine(ContractorAddress $address): string
    {
        $line = trim(($address->street ?? '') . ' ' . ($address->building_number ?? ''));

        if ($address->unit_number !== null && $address->unit_number !== '') {
            $line .= '/' . $address->unit_number;
        }

        return trim($line);
    }
}
