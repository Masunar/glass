<?php

declare(strict_types=1);

namespace App\Services\Offers;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Enum\OfferSumMode;
use App\Models\GlobalParameter;
use App\Support\ParameterTemplate;
use App\Enum\OfferDetailLevel;
use App\Enum\OfferPriceDisplay;
use App\Services\Orders\OrderValue;

/**
 * Zamrożona treść oferty.
 *
 * Wszystko, co klient zobaczy, policzone **raz**, w chwili wystawienia,
 * i zapisane obok oferty. Zlecenie żyje dalej — zmienia się wycena,
 * dochodzą formatki, rusza rabat, ktoś poprawia adres. Oferta zostaje
 * przy tym, co poszło.
 *
 * Dlatego migawka niesie także dane sprzedawcy i teksty ofertowe ze
 * słownika: parametr „warunki płatności” zmieniony w marcu nie ma prawa
 * zmienić oferty z lutego.
 *
 * **Puste pole zostaje puste.** Gdy w słowniku nie ma nazwy firmy ani
 * rachunku, migawka niesie `null`, a wydruk zostawia dziurę. Podstawienie
 * czegokolwiek dałoby dokument, który wygląda na kompletny.
 */
final readonly class OfferSnapshot
{
    /** Parametry z danymi sprzedawcy — klucz w migawce → klucz w słowniku. */
    private const SELLER = [
        'name' => 'company_name',
        'address' => 'company_address',
        'tax_id' => 'company_tax_id',
        'phone' => 'company_phone',
        'email' => 'company_email',
        'bank_account' => 'bank_account_iban',
    ];

    /** Teksty drukowane pod pozycjami. */
    private const TEXTS = [
        'payment_terms' => 'offer_payment_terms',
        'delivery_time' => 'offer_delivery_time',
        'validity' => 'offer_validity_text',
    ];

    public function __construct(
        private OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Order $order,
        OfferDetailLevel $detail,
        OfferSumMode $sumMode,
        OfferPriceDisplay $display,
        ?Carbon $on = null,
    ): array {
        $on ??= Carbon::today();
        $totals = $this->value->totals($order);
        $perList = $this->value->perList($order);

        $lists = [];

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            $id = (int) $list->getKey();
            $row = $perList[$id] ?? [
                'net' => 0.0,
                'included' => (bool) $list->is_included,
                'vat' => null,
                'gross' => null,
                'rates' => [],
            ];

            $lists[] = [
                'id' => $id,
                'number' => (int) $list->number,
                'name' => $list->name,
                'role' => $list->role->value,
                'is_included' => $row['included'],
                'vat_rate' => $list->vat_rate,
                'net' => $this->money($row['net']),
                // Stawki listy po podziale z limitu powierzchni: lista
                // na 8 % przy inwestycji ponad limit ma ich dwie.
                'rates' => $row['rates'],
                'vat' => $row['vat'] === null ? null : $this->money($row['vat']),
                // `null` znaczy, ze stawki nie znamy — oferta brutto
                // nie ma wtedy czego wydrukowac.
                'gross' => $row['gross'] === null ? null : $this->money($row['gross']),
                'comment' => $list->comment,
                // Pozycje trafiaja do migawki wylacznie przy ofercie
                // szczegolowej. Zapisywanie ich „na wszelki wypadek"
                // przy nieszczegolowej znaczyloby, ze dokument niesie
                // dane, ktorych klient nie dostal — a migawka ma byc
                // tym, co poszlo, nie tym, co moglo pojsc.
                'items' => $detail === OfferDetailLevel::DETAILED
                    ? $this->items($list)
                    : [],
            ];
        }

        return [
            'issued_on' => $on->toDateString(),
            'detail_level' => $detail->value,
            'sum_mode' => $sumMode->value,
            'price_display' => $display->value,
            'seller' => $this->parameters(self::SELLER, $on),
            'buyer' => $this->buyer($order),
            'order' => [
                'number' => (int) $order->number,
                'deadline' => $order->client_deadline?->toDateString(),
                'offer_comment' => $order->offer_comment,
            ],
            'lists' => $lists,
            'totals' => $totals->toArray(),
            'sum' => $this->sum($lists, $sumMode, $totals->net, $totals->gross),
            // Teksty **wyrenderowane**, nie szablony: podstawienie
            // w chwili wystawienia, bo oferta z lutego nie ma sie
            // zmieniac, gdy w marcu ktos poprawi numer rachunku.
            'texts' => $this->texts($on),
        ];
    }

    /**
     * Suma na końcu oferty.
     *
     * `COMPONENTS` bierze kwotę zlecenia — ta już pomija alternatywy,
     * bo `OrderValue` liczy wyłącznie listy wliczone. `ALL` dokłada
     * alternatywy, `NONE` nie pokazuje nic.
     *
     * Przy `ALL` brutto liczy się z brutt poszczególnych list, bo
     * alternatywy nie wchodzą do brutto zlecenia. Gdy którejkolwiek
     * listy nie da się policzyć, całość zostaje nieznana.
     *
     * @param list<array<string, mixed>> $lists
     * @return array{mode: string, is_shown: bool, net: string|null, gross: string|null}
     */
    private function sum(array $lists, OfferSumMode $mode, string $net, ?string $gross): array
    {
        if ($mode === OfferSumMode::NONE) {
            return ['mode' => $mode->value, 'is_shown' => false, 'net' => null, 'gross' => null];
        }

        if ($mode === OfferSumMode::COMPONENTS) {
            return ['mode' => $mode->value, 'is_shown' => true, 'net' => $net, 'gross' => $gross];
        }

        $total = 0.0;
        $totalGross = 0.0;
        $grossKnown = true;

        foreach ($lists as $list) {
            $total += (float) ($list['net'] ?? 0);

            if (($list['gross'] ?? null) === null) {
                $grossKnown = false;

                continue;
            }

            $totalGross += (float) $list['gross'];
        }

        return [
            'mode' => $mode->value,
            'is_shown' => true,
            'net' => $this->money($total),
            // Brutto sumy wszystkiego liczy sie z brutt poszczegolnych
            // list, a nie z brutto zlecenia: alternatywy do niego nie
            // wchodza. Gdy ktorejkolwiek listy nie da sie policzyc,
            // calosc zostaje nieznana — suma z dziura nie jest suma.
            'gross' => $grossKnown ? $this->money($totalGross) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(OrderList $list): array
    {
        $rows = [];

        /** @var OrderItem $item */
        foreach ($list->items->sortBy('position') as $item) {
            $pane = $item->pane;
            $processes = [];
            $amount = (float) $item->amount;

            foreach ($item->processes as $entry) {
                $processes[] = $entry->process->name ?? '—';
                $amount += (float) $entry->amount;
            }

            $rows[] = [
                'section' => $item->section->value,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'width_mm' => $pane?->width_mm,
                'height_mm' => $pane?->height_mm,
                'processes' => $processes,
                // Brak ceny jednostkowej zostaje brakiem. Zero znaczyloby
                // „za darmo" i na ofercie bylo by to zdanie wiazace.
                'unit_net_price' => $item->unit_net_price,
                'amount' => $this->money($amount),
            ];
        }

        return $rows;
    }

    /**
     * Nabywca: dane z faktury, gdy je wpisano, inaczej kontrahent.
     *
     * @return array<string, string|null>
     */
    private function buyer(Order $order): array
    {
        $contractor = $order->contractor;

        return [
            'name' => $order->buyer_name ?? $contractor?->displayName(),
            'tax_id' => $order->buyer_tax_id ?? $contractor?->tax_id,
            'address' => $order->buyer_address,
            'contact' => $contractor?->phone,
        ];
    }

    /**
     * Teksty ofertowe z podstawionymi wartościami.
     *
     * Tekst, którego nie da się wypełnić w całości, **nie powstaje** —
     * `ParameterTemplate` zwraca wtedy `null`, a wydruk pomija wiersz.
     * Zdanie urwane w pół wygląda na kompletne i tym jest gorsze od
     * jego braku.
     *
     * @return array<string, string|null>
     */
    private function texts(Carbon $on): array
    {
        $values = [];

        foreach (self::TEXTS as $key => $parameter) {
            $values[$key] = ParameterTemplate::render(
                GlobalParameter::value($parameter, $on),
                $on,
            );
        }

        return $values;
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string|null>
     */
    private function parameters(array $map, Carbon $on): array
    {
        $values = [];

        foreach ($map as $key => $parameter) {
            $values[$key] = GlobalParameter::value($parameter, $on);
        }

        return $values;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
