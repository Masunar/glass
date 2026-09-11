<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Contractor;

/**
 * Ile kontrahent jest nam winien łącznie.
 *
 * Limit kupiecki bez tej liczby był ozdobą: karta pokazywała limit
 * i wartość jednego zlecenia, a pytanie brzmi „czy ten klient mieści
 * się w limicie", nie „czy mieści się to jedno zlecenie".
 *
 * **Liczone hurtem, nie wierszami.** Lista zleceń sprawdza warunek
 * zaliczki dla każdego wiersza; gdyby każdy z nich pytał o saldo swojego
 * kontrahenta osobno, dwieście wierszy dałoby kilkaset zapytań. Stąd
 * `preload()` — jedno pobranie dla wszystkich kontrahentów naraz,
 * a potem odpowiedzi z pamięci.
 *
 * Zlecenia w statusie końcowym nie wchodzą do salda: archiwum i oferty
 * odrzucone nie są długiem.
 */
final class ContractorBalance
{
    /** @var array<int, float> */
    private array $outstanding = [];

    /**
     * Suma wpłat per zlecenie, policzona raz na życie tej instancji.
     * Pamięć jest ważna w obrębie jednego odczytu — po zapisaniu wpłaty
     * saldo liczy już nowa instancja, bo to nowe żądanie.
     *
     * @var array<int, float>|null
     */
    private ?array $paid = null;

    public function __construct(
        private readonly OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * @param list<int> $contractorIds
     */
    public function preload(array $contractorIds): void
    {
        $missing = array_values(array_filter(
            array_unique($contractorIds),
            fn(int $id): bool => !array_key_exists($id, $this->outstanding),
        ));

        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->outstanding[$id] = 0.0;
        }

        /** @var iterable<Order> $orders */
        $orders = Order::query()
            ->with(['lists.items.processes', 'discounts', 'invoiceType'])
            ->whereIn('contractor_id', $missing)
            ->whereHas('status', static fn($query) => $query->where('is_final', false))
            ->get();

        $paid = $this->paidByOrder();


        foreach ($orders as $order) {
            $contractorId = (int) $order->contractor_id;
            $gross = $this->grossOf($order);
            $due = $gross - ($paid[(int) $order->getKey()] ?? 0.0);

            // Nadplata na jednym zleceniu nie zmniejsza dlugu z innych —
            // to dwie osobne sprawy i ksiegowa rozlicza je osobno.
            $this->outstanding[$contractorId] += max($due, 0.0);
        }
    }

    /** Ile kontrahent jest winien ze wszystkich otwartych zleceń. */
    public function outstanding(Contractor $contractor): string
    {
        $id = (int) $contractor->getKey();

        if (!array_key_exists($id, $this->outstanding)) {
            $this->preload([$id]);
        }

        return $this->money($this->outstanding[$id] ?? 0.0);
    }

    /**
     * Czy kontrahent mieści się w limicie razem z tym zleceniem.
     *
     * `null` oznacza „nie wiadomo": zlecenie bez typu faktury nie ma
     * znanej kwoty brutto, a limit jest kwotą brutto.
     */
    public function withinLimit(Order $order): ?bool
    {
        $contractor = $order->contractor;

        if ($contractor === null) {
            return null;
        }

        if ($this->value->totals($order)->gross === null) {
            return null;
        }

        return (float) $this->outstanding($contractor) <= (float) $contractor->credit_limit;
    }

    /**
     * Suma wpłat na zlecenie, w walucie rozliczeniowej. Korekty są
     * zwykłymi wierszami z kwotą ujemną, więc sumują się same.
     *
     * @return array<int, float>
     */
    private function paidByOrder(): array
    {
        if ($this->paid !== null) {
            return $this->paid;
        }

        /** @var array<int, string> $sums */
        $sums = Payment::query()
            ->selectRaw('order_id, SUM(amount_base) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id')
            ->all();

        $paid = [];

        foreach ($sums as $orderId => $total) {
            $paid[(int) $orderId] = (float) $total;
        }

        return $this->paid = $paid;
    }

    private function grossOf(Order $order): float
    {
        $totals = $this->value->totals($order);

        // Bez typu faktury nie znamy stawki, wiec do dlugu bierzemy
        // netto. Zanizone, ale nie zmyslone — a zlecenie bez typu
        // faktury i tak nie przejdzie dalej.
        return (float) ($totals->gross ?? $totals->net);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
