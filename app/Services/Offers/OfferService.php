<?php

declare(strict_types=1);

namespace App\Services\Offers;

use Carbon\Carbon;
use App\Enum\ListRole;
use App\Models\Order;
use App\Models\Offer;
use App\Models\OrderList;
use App\Enum\OfferStatus;
use App\Enum\OfferSumMode;
use App\Support\Normalize;
use App\Services\AuditTrail;
use App\Enum\OfferDetailLevel;
use App\Enum\OfferPriceDisplay;
use App\Models\GlobalParameter;
use App\Services\Orders\OrderValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Oferty zlecenia.
 *
 * Oferta powstaje **wystawieniem** i od tej chwili jest niezmienna.
 * Nie ma edycji ani szkicu: dokument, który da się jeszcze poprawić,
 * nie jest dowodem na to, co dostał klient. Poprawka to następne
 * wystawienie — `24046/1`, `24046/2` — a poprzednia wersja zostaje.
 *
 * **Status oferty nie rusza statusu zlecenia.** To była decyzja
 * Marcina i ma sens: klient może odrzucić wariant i poprosić o drugi,
 * a zlecenie ma wtedy żyć dalej. Przyjęcie oferty robi natomiast jedną
 * rzecz ze zleceniem — włącza wybrany wariant do kwoty i wyłącza
 * pozostałe alternatywy. To nie jest zmiana statusu, tylko zapisanie
 * decyzji, która i tak musiałaby zostać wyklikana ręcznie.
 */
final readonly class OfferService
{
    public function __construct(
        private OfferSnapshot $snapshot = new OfferSnapshot(),
        private OrderValue $value = new OrderValue(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Wystawienie oferty.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null, number: string|null}
     */
    public function issue(int $orderId, array $input): array
    {
        $order = $this->order($orderId);

        $validator = Validator::make($input, [
            'detail_level' => ['nullable', 'string', 'in:summary,detailed'],
            'sum_mode' => ['nullable', 'string', 'in:components,all,none'],
            'price_display' => ['nullable', 'string', 'in:net,gross'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'comment.max' => 'Komentarz do oferty jest za długi.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null, 'number' => null];
        }

        // Nieszczegolowa jest domyslna (Z-Z-05): rozpiska pokazuje
        // klientowi wszystko, z czego sklada sie cena. Kto chce ja
        // pokazac, robi to swiadomie.
        $detail = OfferDetailLevel::tryFrom((string) ($input['detail_level'] ?? ''))
            ?? OfferDetailLevel::SUMMARY;

        // Suma sama pomija warianty (Z-Z-06): lista juz wie, czym jest,
        // a reczne wylaczanie dziala tylko wtedy, gdy ktos pamieta.
        $sumMode = OfferSumMode::tryFrom((string) ($input['sum_mode'] ?? ''))
            ?? OfferSumMode::COMPONENTS;

        $display = OfferPriceDisplay::tryFrom((string) ($input['price_display'] ?? ''))
            ?? OfferPriceDisplay::NET;

        $totals = $this->value->totals($order);

        if ($this->isEmpty($order)) {
            return [
                'errors' => ['offer' => ['Zlecenie nie ma ani jednej pozycji — nie ma czego zaoferować.']],
                'id' => null,
                'number' => null,
            ];
        }

        // Brutto wymaga znanej stawki. Lepiej zatrzymac sie tutaj niz
        // wyslac klientowi kwote, ktorej nikt nie policzyl.
        if ($display === OfferPriceDisplay::GROSS && $totals->gross === null) {
            return [
                'errors' => ['price_display' => [
                    $totals->unknownReason
                        ?? 'Zlecenie nie ma znanej stawki VAT, więc oferty brutto nie da się wystawić.',
                ]],
                'id' => null,
                'number' => null,
            ];
        }

        $on = Carbon::today();
        $days = GlobalParameter::number('offer_validity_days', $on);

        /** @var Offer $offer */
        $offer = Offer::query()->create([
            'order_id' => (int) $order->getKey(),
            'sequence' => $this->nextSequence($order),
            'status' => OfferStatus::ISSUED->value,
            'detail_level' => $detail->value,
            'sum_mode' => $sumMode->value,
            'price_display' => $display->value,
            'is_variant' => $this->hasAlternatives($order),
            // Brak parametru to brak terminu waznosci, a nie „wazna
            // dzisiaj". Data wyliczona z zera bylaby ofertą, ktora
            // wygasla w chwili wystawienia.
            'valid_until' => $days === null ? null : $on->copy()->addDays((int) $days),
            'net' => $totals->net,
            'vat' => $totals->vat,
            'gross' => $totals->gross,
            'snapshot' => $this->snapshot->build($order, $detail, $sumMode, $display, $on),
            'comment' => Normalize::text($input['comment'] ?? null),
            'issued_by' => Auth::id(),
            'issued_at' => now(),
        ]);

        $this->write($order, 'oferta ' . $offer->number(), null, $this->describe($offer), 'offer_issued');

        return ['errors' => [], 'id' => (int) $offer->getKey(), 'number' => $offer->number()];
    }

    /**
     * Oznaczenie oferty jako wysłanej.
     *
     * Osobna akcja, bo wysyłka mailem to dopiero druga część modułu,
     * a ofertę pobraną i wysłaną z własnej skrzynki też trzeba umieć
     * odnotować — inaczej historia mówi „wystawiona" o czymś, co klient
     * dostał tydzień temu.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function markSent(int $orderId, int $offerId): array
    {
        $offer = $this->offerOf($orderId, $offerId);

        if ($offer === null) {
            return ['errors' => ['offer' => ['Ta oferta nie należy do tego zlecenia.']]];
        }

        if ($offer->status !== OfferStatus::ISSUED) {
            return ['errors' => ['offer' => ['Tę ofertę już oznaczono jako ' . $offer->status->label() . '.']]];
        }

        $offer->update(['status' => OfferStatus::SENT->value, 'sent_at' => now()]);

        $this->write(
            $this->order($orderId),
            'oferta ' . $offer->number(),
            OfferStatus::ISSUED->label(),
            OfferStatus::SENT->label(),
            'offer_sent',
        );

        return ['errors' => []];
    }

    /**
     * Przyjęcie oferty przez klienta.
     *
     * `$listId` wskazuje przyjęty wariant. Wtedy dzieje się rzecz,
     * która jest całą wartością tej akcji: wskazana lista wchodzi do
     * kwoty, a **pozostałe alternatywy z niej wypadają**. Listy będące
     * składnikami (pomieszczenia) zostają nietknięte — wybór wariantu
     * nie jest wyborem między pomieszczeniami.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function accept(int $orderId, int $offerId, mixed $listId = null): array
    {
        $offer = $this->offerOf($orderId, $offerId);

        if ($offer === null) {
            return ['errors' => ['offer' => ['Ta oferta nie należy do tego zlecenia.']]];
        }

        if (!$offer->status->isOpen()) {
            return ['errors' => ['offer' => ['Ta oferta jest już ' . mb_strtolower($offer->status->label()) . '.']]];
        }

        $order = $this->order($orderId);
        $chosen = null;

        if (is_numeric($listId)) {
            $chosen = $this->listOf($order, (int) $listId);

            if ($chosen === null) {
                return ['errors' => ['accepted_list_id' => ['Ta lista nie należy do tego zlecenia.']]];
            }
        }

        if ($chosen !== null) {
            $this->chooseVariant($order, $chosen);
        }

        // Stan sprzed zapisu, bo `update()` nadpisze go zaraz nizej,
        // a wpis w dzienniku ma pokazywac zmiane, nie dwa razy to samo.
        $before = $offer->status->label();

        $offer->update([
            'status' => OfferStatus::ACCEPTED->value,
            'accepted_list_id' => $chosen === null ? null : (int) $chosen->getKey(),
            'decided_at' => now(),
        ]);

        $this->write(
            $order,
            'oferta ' . $offer->number(),
            $before,
            OfferStatus::ACCEPTED->label()
                . ($chosen === null ? '' : ', wariant: lista ' . $chosen->number),
            'offer_accepted',
        );

        return ['errors' => []];
    }

    /**
     * Odrzucenie oferty przez klienta. Powód jest wymagany.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>}
     */
    public function reject(int $orderId, int $offerId, array $input): array
    {
        $offer = $this->offerOf($orderId, $offerId);

        if ($offer === null) {
            return ['errors' => ['offer' => ['Ta oferta nie należy do tego zlecenia.']]];
        }

        if (!$offer->status->isOpen()) {
            return ['errors' => ['offer' => ['Ta oferta jest już ' . mb_strtolower($offer->status->label()) . '.']]];
        }

        $validator = Validator::make($input, [
            'rejection_reason' => ['required', 'string', 'max:200'],
        ], [
            // Powod nie jest formalnoscia: bez niego po pol roku nie da
            // sie powiedziec, czy przegrywamy cena, czy terminem.
            'rejection_reason.required' => 'Podaj powód odrzucenia — bez niego nie wiadomo, dlaczego oferta przepadła.',
            'rejection_reason.max' => 'Powód jest za długi.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages];
        }

        $reason = Normalize::text($input['rejection_reason']);

        $offer->update([
            'status' => OfferStatus::REJECTED->value,
            'rejection_reason' => $reason,
            'decided_at' => now(),
        ]);

        $this->write(
            $this->order($orderId),
            'oferta ' . $offer->number(),
            null,
            OfferStatus::REJECTED->label() . ': ' . $reason,
            'offer_rejected',
        );

        return ['errors' => []];
    }

    /**
     * Wybór wariantu: wskazana alternatywa wchodzi, reszta wypada.
     *
     * Składniki zostają nietknięte — wybór między szkłem 8 a 6 mm nie
     * jest wyborem między kuchnią a łazienką.
     */
    private function chooseVariant(Order $order, OrderList $chosen): void
    {
        if (!$chosen->is_included) {
            $chosen->update(['is_included' => true]);
        }

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if ((int) $list->getKey() === (int) $chosen->getKey()) {
                continue;
            }

            if ($list->role !== ListRole::ALTERNATIVE || !$list->is_included) {
                continue;
            }

            $list->update(['is_included' => false]);
        }
    }

    private function nextSequence(Order $order): int
    {
        return ((int) Offer::query()
            ->where('order_id', $order->getKey())
            ->max('sequence')) + 1;
    }

    private function hasAlternatives(Order $order): bool
    {
        foreach ($order->lists as $list) {
            if ($list->role === ListRole::ALTERNATIVE) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty(Order $order): bool
    {
        foreach ($order->lists as $list) {
            if ($list->items->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function order(int $orderId): Order
    {
        /** @var Order */
        return Order::query()
            ->with([
                'contractor',
                'invoiceType',
                'discounts',
                'lists.items.pane',
                'lists.items.processes.process',
            ])
            ->findOrFail($orderId);
    }

    private function offerOf(int $orderId, int $offerId): ?Offer
    {
        /** @var Offer|null */
        return Offer::query()
            ->with('order')
            ->where('order_id', $orderId)
            ->find($offerId);
    }

    private function listOf(Order $order, int $listId): ?OrderList
    {
        /** @var OrderList|null */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->find($listId);
    }

    private function describe(Offer $offer): string
    {
        $parts = [
            $offer->detail_level === OfferDetailLevel::DETAILED ? 'szczegółowa' : 'nieszczegółowa',
            mb_strtolower($offer->price_display->label()),
            $offer->net . ' zł netto',
        ];

        if ($offer->is_variant) {
            $parts[] = 'wariantowa';
        }

        return implode(', ', $parts);
    }

    private function write(Order $order, string $field, ?string $before, ?string $after, string $event): void
    {
        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => $field, 'before' => $before, 'after' => $after]],
            $event,
        );
    }
}
