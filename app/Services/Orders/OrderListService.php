<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\ListRole;
use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\InvoiceType;
use App\Support\Normalize;
use App\Services\AuditTrail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Listy zlecenia.
 *
 * Lista obsługuje dwa przypadki naraz i to jest sedno całego mechanizmu:
 * **kompozycję** (kilka pomieszczeń = kilka list, wszystkie wliczone)
 * i **wariantowanie oferty** (szkło 8 mm obok 6 mm, jedna wliczona,
 * reszta zostaje w historii zlecenia). Bez jawnego rozróżnienia klient
 * dostaje sumę dwóch alternatyw jako cenę.
 *
 * Dotąd zlecenie dostawało jedną listę przy zakładaniu i nie było jak
 * dołożyć drugiej — przy czym cała maszyneria wokół list już działała
 * i czekała pusta: `is_included` decyduje o wartości zlecenia,
 * `is_on_hold` blokuje przejście na produkcję, rola odcina alternatywy
 * od hali, a komentarz jedzie na kartę operatora.
 *
 * **`is_included` i `is_on_hold` to dwie różne rzeczy.** Wyłączona nie
 * należy do zlecenia (odrzucona alternatywa, kwota zero). Wstrzymana
 * należy i jest wyceniona, ale nie może iść na produkcję.
 */
final readonly class OrderListService
{
    private const MAX_LISTS = 30;

    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
        private OrderDeadline $deadline = new OrderDeadline(),
    ) {
    }

    /**
     * Założenie albo zapis listy.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function save(int $orderId, array $input, ?int $listId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
            'role' => ['nullable', 'string', 'in:component,alternative'],
            'is_included' => ['nullable', 'boolean'],
            'is_on_hold' => ['nullable', 'boolean'],
            // `null` znaczy „jak w typie faktury", a nie „zero" — 0 %
            // to prawdziwa stawka (eksport, odwrotne obciazenie), wiec
            // bez rozroznienia nie da sie wystawic ani jednej z nich.
            'vat_rate' => ['nullable', 'integer', Rule::in($this->rates())],
        ], [
            'name.max' => 'Nazwa listy jest za długa.',
            'comment.max' => 'Komentarz jest za długi.',
            'vat_rate.in' => 'Takiej stawki nie ma w słowniku typów faktur.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $list = $listId === null ? null : $this->listOf($order, $listId);

        if ($listId !== null && $list === null) {
            return ['errors' => ['list' => ['Ta lista nie należy do tego zlecenia.']], 'id' => null];
        }

        if ($list === null && $this->count($order) >= self::MAX_LISTS) {
            return [
                'errors' => ['list' => ['Zlecenie ma już ' . self::MAX_LISTS . ' list — to nie jest lista, tylko osobne zlecenie.']],
                'id' => null,
            ];
        }

        $role = ListRole::tryFrom((string) ($input['role'] ?? '')) ?? ListRole::COMPONENT;
        $before = $list === null ? null : $this->describe($list);

        if ($list === null) {
            /** @var OrderList $list */
            $list = OrderList::query()->create([
                'order_id' => (int) $order->getKey(),
                'number' => $this->nextNumber($order),
                'name' => Normalize::text($input['name'] ?? null),
                'role' => $role->value,
                // Alternatywa zaklada sie wylaczona: wariant, ktory
                // dolicza sie do kwoty w chwili powstania, to dokladnie
                // ten blad, przed ktorym rola ma chronic.
                'is_included' => $role === ListRole::COMPONENT,
                'vat_rate' => $this->rate($input),
                'comment' => Normalize::text($input['comment'] ?? null),
            ]);
        } else {
            $list->update([
                'name' => Normalize::text($input['name'] ?? null),
                'role' => $role->value,
                'is_included' => (bool) ($input['is_included'] ?? $list->is_included),
                'is_on_hold' => (bool) ($input['is_on_hold'] ?? $list->is_on_hold),
                'vat_rate' => array_key_exists('vat_rate', $input)
                    ? $this->rate($input)
                    : $list->vat_rate,
                'comment' => Normalize::text($input['comment'] ?? null),
            ]);
        }

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'lista ' . $list->number,
                'before' => $before,
                'after' => $this->describe($list->refresh()),
            ]],
            $listId === null ? 'list_added' : 'list_changed',
        );

        // Wlaczenie albo wylaczenie listy zmienia, ktore formatki licza
        // sie do terminu.
        $this->deadline->refresh((int) $order->getKey());

        return ['errors' => [], 'id' => (int) $list->getKey()];
    }

    /**
     * Usunięcie listy.
     *
     * Listy z pozycjami nie kasujemy i ostatniej też nie. Pierwsze, bo
     * kasowanie wyceny jednym kliknięciem to strata pracy, której nie da
     * się cofnąć — od wyłączania wariantu jest `is_included`. Drugie, bo
     * zlecenie bez ani jednej listy nie ma gdzie trzymać pozycji.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function delete(int $orderId, int $listId): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $list = $this->listOf($order, $listId);

        if ($list === null) {
            return ['errors' => ['list' => ['Ta lista nie należy do tego zlecenia.']]];
        }

        if ($this->count($order) <= 1) {
            return ['errors' => ['list' => ['To jedyna lista zlecenia — nie ma jej czym zastąpić.']]];
        }

        if (OrderItem::query()->where('order_list_id', $list->getKey())->exists()) {
            return ['errors' => ['list' => [
                'Lista ma pozycje. Przenieś je albo wyłącz listę z kwoty — kasowanie zabrałoby wycenę bez śladu.',
            ]]];
        }

        $number = (int) $list->number;
        $list->delete();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'lista ' . $number, 'before' => 'istniała', 'after' => null]],
            'list_deleted',
        );

        return ['errors' => []];
    }

    /**
     * Przeniesienie pozycji na inną listę.
     *
     * Cena zostaje nietknięta: przeniesienie nie zmienia ani materiału,
     * ani wymiaru, ani kontrahenta, więc nie ma powodu przeliczać wyceny
     * i kasować jej ścieżki.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function moveItem(int $orderId, int $itemId, mixed $targetId): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        /** @var OrderItem|null $item */
        $item = OrderItem::query()
            ->whereHas('list', static fn($query) => $query->where('order_id', $order->getKey()))
            ->find($itemId);

        if ($item === null) {
            return ['errors' => ['item' => ['Ta pozycja nie należy do tego zlecenia.']]];
        }

        $target = is_numeric($targetId) ? $this->listOf($order, (int) $targetId) : null;

        if ($target === null) {
            return ['errors' => ['order_list_id' => ['Ta lista nie należy do tego zlecenia.']]];
        }

        if ((int) $item->order_list_id === (int) $target->getKey()) {
            return ['errors' => []];
        }

        $from = $item->list?->number;

        $item->update([
            'order_list_id' => (int) $target->getKey(),
            'position' => $this->nextPosition($target),
        ]);

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'pozycja #' . $item->getKey(),
                'before' => $from === null ? null : 'lista ' . $from,
                'after' => 'lista ' . $target->number,
            ]],
            'item_moved',
        );

        // Przeniesienie na liste niewliczona zabiera formatke z terminu.
        $this->deadline->refresh((int) $order->getKey());

        return ['errors' => []];
    }

    private function listOf(Order $order, int $listId): ?OrderList
    {
        /** @var OrderList|null */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->find($listId);
    }

    private function count(Order $order): int
    {
        return OrderList::query()->where('order_id', $order->getKey())->count();
    }

    /**
     * Numer kolejnej listy idzie od najwyższego istniejącego.
     *
     * Skasowanie listy **nie przenumerowuje pozostałych** — „lista 3"
     * w komentarzu produkcyjnym ma dalej znaczyć tę samą listę po
     * usunięciu drugiej. Numer tej z końca może się po skasowaniu
     * powtórzyć i to jest nieszkodliwe: usunąć da się tylko listę pustą,
     * więc nie ma czego pomylić.
     */
    private function nextNumber(Order $order): int
    {
        return ((int) OrderList::query()
            ->where('order_id', $order->getKey())
            ->max('number')) + 1;
    }

    private function nextPosition(OrderList $list): int
    {
        return ((int) OrderItem::query()
            ->where('order_list_id', $list->getKey())
            ->max('position')) + 10;
    }

    /**
     * Stawki dopuszczalne na liscie to te, ktore sa w slowniku typow
     * faktur. Slownik jest miejscem, gdzie ksiegowosc trzyma stawki —
     * druga, wlasna lista w kodzie rozjechalaby sie z nia po pierwszej
     * zmianie przepisow.
     *
     * @return list<int>
     */
    private function rates(): array
    {
        /** @var list<int> */
        return InvoiceType::query()
            ->distinct()
            ->orderBy('vat_rate')
            ->pluck('vat_rate')
            ->map(static fn(mixed $rate): int => (int) $rate)
            ->all();
    }

    /** @param array<string, mixed> $input */
    private function rate(array $input): ?int
    {
        $value = $input['vat_rate'] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function describe(OrderList $list): string
    {
        $parts = [$list->name ?? 'bez nazwy', $list->role->value];

        if ($list->vat_rate !== null) {
            $parts[] = 'VAT ' . $list->vat_rate . '%';
        }

        if (!$list->is_included) {
            $parts[] = 'nie wchodzi do kwoty';
        }

        if ($list->is_on_hold) {
            $parts[] = 'wstrzymana';
        }

        return implode(', ', $parts);
    }
}
