<?php

declare(strict_types=1);

namespace App\Services\Offers;

use Carbon\Carbon;
use App\Models\Offer;
use App\Models\Order;
use App\Enum\ListRole;
use App\Models\OrderList;
use App\Enum\OfferStatus;
use App\Services\Orders\OrderTabs;
use App\Services\Orders\OrderValue;

/**
 * Ekrany ofert: historia przy zleceniu i lista wszystkich ofert.
 *
 * Historia odpowiada na pytanie ze zgłoszenia Z-Ż-03 — „czy ostatnia
 * oferta była netto/brutto/wariantowa" — więc te trzy rzeczy stoją
 * w wierszu, a nie w szczegółach.
 */
final readonly class OfferBoard
{
    public function __construct(
        private OrderTabs $tabs = new OrderTabs(),
        private OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * Oferty jednego zlecenia plus to, czego potrzebuje wystawienie.
     *
     * @return array<string, mixed>
     */
    public function forOrder(int $orderId, ?Carbon $on = null): array
    {
        $on ??= Carbon::today();

        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor',
                'invoiceType',
                'discounts',
                'lists.items.processes',
            ])
            ->findOrFail($orderId);

        /** @var iterable<Offer> $offers */
        $offers = Offer::query()
            ->with(['issuer', 'order', 'acceptedList'])
            ->where('order_id', $orderId)
            ->orderByDesc('sequence')
            ->get();

        $rows = [];

        foreach ($offers as $offer) {
            $rows[] = $this->row($offer, $on);
        }

        $totals = $this->value->totals($order);

        return [
            'order' => [
                'id' => (int) $order->getKey(),
                'number' => (int) $order->number,
                'status' => $order->status?->name,
                'contractor' => $order->contractor?->displayName(),
            ],
            'tabs' => $this->tabs->counts($order),
            'offers' => $rows,
            // Stan zlecenia na dzis, zeby dalo sie porownac z tym, co
            // poszlo do klienta. Bez tego historia mowi tylko „bylo",
            // a pytanie brzmi zwykle „czy cos sie od tego czasu zmienilo".
            'current' => [
                'net' => $totals->net,
                'gross' => $totals->gross,
                'unknown_net' => $totals->unknownNet,
                'unknown_reason' => $totals->unknownReason,
                'mixed_vat' => $totals->mixedVat,
            ],
            'variants' => $this->variants($order),
        ];
    }

    /**
     * Lista wszystkich ofert — ekran „Oferty" w menu modułu.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function board(array $filters = [], ?Carbon $on = null): array
    {
        $on ??= Carbon::today();
        $status = OfferStatus::tryFrom((string) ($filters['status'] ?? ''));

        $query = Offer::query()
            ->with(['order.contractor', 'issuer'])
            ->orderByDesc('issued_at');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        /** @var iterable<Offer> $offers */
        $offers = $query->limit(200)->get();

        $rows = [];

        foreach ($offers as $offer) {
            $rows[] = $this->row($offer, $on) + [
                // Bez `?->` po `order`: `offers.order_id` jest NOT NULL.
                'contractor' => $offer->order->contractor?->displayName(),
                'order_id' => (int) $offer->order_id,
            ];
        }

        return ['offers' => $rows, 'counts' => $this->counts()];
    }

    /**
     * Ile ofert czeka na decyzję klienta — w całej bazie.
     *
     * Pulpit liczył otwarte spośród dwustu ostatnich wierszy listy, więc
     * przy większej liczbie ofert kafelek po cichu przestawał rosnąć:
     * starsza, wciąż otwarta oferta wypadała poza limit i z licznika.
     * „Otwarta" zostaje zdefiniowana w jednym miejscu — `isOpen()`.
     */
    public function openCount(): int
    {
        $open = array_values(array_map(
            static fn(OfferStatus $status): string => $status->value,
            array_filter(OfferStatus::cases(), static fn(OfferStatus $status): bool => $status->isOpen()),
        ));

        return Offer::query()->whereIn('status', $open)->count();
    }

    /**
     * Skrót dla karty zlecenia: ile ofert i jak wyglądała ostatnia.
     *
     * @return array<string, mixed>|null
     */
    public function summary(Order $order, ?Carbon $on = null): ?array
    {
        /** @var Offer|null $last */
        $last = Offer::query()
            ->with('order')
            ->where('order_id', $order->getKey())
            ->orderByDesc('sequence')
            ->first();

        if ($last === null) {
            return null;
        }

        return [
            'count' => Offer::query()->where('order_id', $order->getKey())->count(),
            'last' => $this->row($last, $on ?? Carbon::today()),
        ];
    }

    /**
     * Warianty do wyboru przy przyjęciu oferty.
     *
     * Same alternatywy: przyjęcie wariantu nie jest wyborem między
     * pomieszczeniami, więc listy będące składnikami nie mają się tu
     * po co pojawiać.
     *
     * @return list<array<string, mixed>>
     */
    private function variants(Order $order): array
    {
        $perList = $this->value->perList($order);
        $rows = [];

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            if ($list->role !== ListRole::ALTERNATIVE) {
                continue;
            }

            $id = (int) $list->getKey();

            $rows[] = [
                'id' => $id,
                'number' => (int) $list->number,
                'name' => $list->name,
                'is_included' => (bool) $list->is_included,
                'net' => number_format($perList[$id]['net'] ?? 0.0, 2, '.', ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Offer $offer, Carbon $on): array
    {
        return [
            'id' => (int) $offer->getKey(),
            'number' => $offer->number(),
            'sequence' => (int) $offer->sequence,
            'status' => $offer->status->value,
            'status_label' => $offer->status->label(),
            'detail_level' => $offer->detail_level->value,
            'sum_mode' => $offer->sum_mode->value,
            'price_display' => $offer->price_display->value,
            'is_variant' => (bool) $offer->is_variant,
            'net' => $offer->net,
            'vat' => $offer->vat,
            'gross' => $offer->gross,
            'valid_until' => $offer->valid_until?->toDateString(),
            // Wygasla to nie to samo co odrzucona: klient nie
            // odpowiedzial, a termin minal. Jedno mowi o kliencie,
            // drugie o nas.
            'is_expired' => $offer->isExpired($on),
            'comment' => $offer->comment,
            'rejection_reason' => $offer->rejection_reason,
            'accepted_list' => $offer->acceptedList?->number,
            // Stan otwarty jako pojecie domenowe, nie porownanie
            // statusow na ekranie: pulpit i lista musza rozumiec przez
            // „czeka u klienta" to samo.
            'is_open' => $offer->status->isOpen(),
            'issued_at' => $offer->issued_at->toDateTimeString(),
            'issued_by' => $this->personName($offer),
            'issued_by_id' => $offer->issued_by === null ? null : (int) $offer->issued_by,
            'sent_at' => $offer->sent_at?->toDateTimeString(),
        ];
    }

    private function personName(Offer $offer): ?string
    {
        $user = $offer->issuer;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = ['all' => Offer::query()->count()];

        foreach (OfferStatus::cases() as $status) {
            $counts[$status->value] = Offer::query()->where('status', $status->value)->count();
        }

        return $counts;
    }
}
