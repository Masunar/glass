<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Status;
use App\DTO\Orders\NextStep;
use App\Enum\DeliveryMethod;
use App\Enum\ContractorType;
use App\Models\StatusTransition;

/**
 * Co można zrobić z tym zleceniem i czego brakuje, żeby móc.
 *
 * Przejścia i ich warunki są danymi w `status_transitions`, nie kodem —
 * dodanie etapu to wiersz w tabeli. Ta klasa je wyłącznie rozstrzyga.
 *
 * **Reguła nierozstrzygalna blokuje przejście, a nie przepuszcza go.**
 * Część warunków dotyczy modułów, których jeszcze nie ma (wpłaty,
 * ewidencja produkcji). Gdyby brak modułu oznaczał „warunek spełniony",
 * zlecenie mogłoby trafić do produkcji bez zaliczki i bez sprawdzenia
 * limitu — czyli dokładnie to, przed czym ten mechanizm ma chronić.
 * Zamknięcie na głucho jest niewygodne i to jest zamierzone: widać,
 * czego brakuje, zamiast dowiedzieć się o tym z faktury.
 */
final readonly class OrderNextStep
{
    public function __construct(
        private OrderValue $value = new OrderValue(),
    ) {
    }

    /**
     * Wszystkie przejścia wychodzące z bieżącego statusu, w kolejności
     * z katalogu — pierwsze dostępne jest tym, które ekran pokazuje
     * jako przycisk.
     *
     * @return list<NextStep>
     */
    public function forOrder(Order $order): array
    {
        $transitions = StatusTransition::query()
            ->with('toStatus')
            ->where('from_status_id', $order->status_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $steps = [];

        foreach ($transitions as $transition) {
            $target = $transition->toStatus;

            if (!$target instanceof Status) {
                continue;
            }

            $conditions = $transition->conditions ?? [];
            [$available, $reason, $unknown] = $this->check($order, $conditions);

            $steps[] = new NextStep(
                transitionId: (int) $transition->getKey(),
                target: $target,
                label: $transition->button_label ?? $target->name,
                available: $available,
                blockedBy: $reason,
                unknown: $unknown,
                needsReason: $this->needsReason($conditions),
            );
        }

        return $steps;
    }

    /** Pierwsze przejście, które da się teraz wykonać. */
    public function firstAvailable(Order $order): ?NextStep
    {
        foreach ($this->forOrder($order) as $step) {
            if ($step->available) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Czy brakujący warunek da się uzupełnić treścią podaną razem
     * z akcją — dziś dotyczy to wyłącznie powodu anulowania.
     *
     * @param array<int, array<string, mixed>> $conditions
     */
    private function needsReason(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (($condition['rule'] ?? null) === 'cancellation_reason_set') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @return array{0: bool, 1: string|null, 2: bool}
     */
    private function check(Order $order, array $conditions): array
    {
        $missing = null;

        foreach ($conditions as $condition) {
            $rule = (string) ($condition['rule'] ?? '');
            $message = isset($condition['message']) ? (string) $condition['message'] : $rule;
            $value = $condition['value'] ?? null;

            $result = $this->evaluate($order, $rule, $value);

            // Warunek niespełniony wygrywa z nierozstrzygalnym, nawet gdy
            // stoi w katalogu później. Człowiek ma dostać rzecz, którą da
            // się zrobić — „dodaj rysunki" zamiast „poczekaj na moduł
            // wpłat", skoro i tak brakuje obu.
            if ($result === false) {
                return [false, $message, false];
            }

            if ($result === null) {
                $missing ??= $this->missingModule($rule);
            }
        }

        if ($missing !== null) {
            return [false, $missing, true];
        }

        return [true, null, false];
    }

    /**
     * `null` oznacza „nie wiadomo" — reguła czeka na moduł, którego nie ma.
     */
    private function evaluate(Order $order, string $rule, mixed $value): ?bool
    {
        return match ($rule) {
            'has_enabled_list' => $order->lists->contains(
                static fn($list): bool => (bool) $list->is_included,
            ),
            'no_list_on_hold' => !$order->lists->contains(
                static fn($list): bool => (bool) $list->is_on_hold,
            ),
            'total_above_zero' => (float) $this->value->net($order) > 0.0,
            'customer_complete' => $this->customerComplete($order),
            'invoice_data_complete' => $order->invoice_type_id !== null,
            'handover_method_is' => $order->delivery_method === DeliveryMethod::tryFrom((string) $value),
            'pickup_point_set' => $order->pickup_location_id !== null,
            'no_open_complaint' => !$order->has_open_claim,
            'cancellation_reason_set' => $order->cancellation_reason !== null,
            // Komplet rysunkow deklaruje czlowiek, nie licznik plikow:
            // zlecenie na proste docinki nie potrzebuje zadnego rysunku.
            'all_drawings_added' => $order->drawings_complete_at !== null,

            // Poniższe czekają na moduły, których nie ma. Nie zgadujemy.
            'prepayment_or_credit_limit' => null,
            'balance_is_zero' => null,
            'all_production_tasks_done' => null,
            'rejection_reason_set' => null,

            default => null,
        };
    }

    private function missingModule(string $rule): string
    {
        return match ($rule) {
            'prepayment_or_credit_limit', 'balance_is_zero' => 'Wymaga modułu wpłat — jeszcze go nie ma.',
            'all_production_tasks_done' => 'Wymaga ewidencji etapów produkcji — jeszcze jej nie ma.',
            'rejection_reason_set' => 'Wymaga pola „powód nieprzyjęcia oferty" — jeszcze go nie ma.',
            default => sprintf('Nieznany warunek „%s" — nie da się go rozstrzygnąć.', $rule),
        };
    }

    /**
     * Firma bez NIP-u nie da się zafakturować, a osoba prywatna nie musi
     * go mieć. Kartoteka pilnuje tego przy zapisie, ale zlecenie mogło
     * powstać wcześniej.
     */
    private function customerComplete(Order $order): bool
    {
        $contractor = $order->contractor;

        if ($contractor === null || $contractor->name === '') {
            return false;
        }

        return $contractor->type !== ContractorType::COMPANY || $contractor->tax_id !== null;
    }
}
