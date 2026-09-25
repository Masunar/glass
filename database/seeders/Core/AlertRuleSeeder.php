<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\AlertRule;
use Salvon\Database\Seeder;
use App\Alerts\ConditionCatalog;
use App\Enum\AlertConditionType;

/**
 * Reguły startowe — **propozycja z dokumentacji, nie decyzja**.
 *
 * Kategorie i przykłady pochodzą z `docs/00-przeglad.md` §5.2. Nic tu
 * nie jest wymyślone ponad ten spis, a progi (jeden dzień po terminie,
 * status `PRODUKCJA` bez wpłaty) to **najostrożniejszy odczyt** tych
 * przykładów, nie ustalenie z Marcinem. Do przejrzenia na ekranie
 * `/alerts`, gdzie każdy z nich jest polem.
 *
 * **Próg magazynowy nie jest tu ustawiany.** Reguła „poniżej minimum"
 * nie ma parametru: minimum stoi przy pozycji magazynu i tam należy.
 * Drugi próg, w regule, byłby drugim źródłem tej samej prawdy.
 *
 * Kategoria **Akceptacja nie dostaje żadnej reguły**: „rabat powyżej
 * progu czeka na zatwierdzenie" wymaga kolejki akceptacji, a jej
 * kolumny stoją dziś puste. Reguła bez danych zapalałaby się nigdy
 * i wyglądałaby na działającą.
 *
 * Zasiew jest **zachowawczy**: reguła o danym kodzie, która już
 * istnieje, nie jest ruszana. Administrator zmienia próg raz, a nie po
 * każdym wdrożeniu.
 */
class AlertRuleSeeder extends Seeder
{
    /** @var list<array{type: AlertConditionType, code: string, name: string, label: string, params: array<string, mixed>}> */
    private const RULES = [
        [
            'type' => AlertConditionType::ORDER_OVERDUE,
            'code' => 'order_overdue',
            'name' => 'Zlecenie po terminie',
            'label' => 'po terminie',
            'params' => ['days' => 1],
        ],
        [
            'type' => AlertConditionType::ORDER_CONTACT_OVERDUE,
            'code' => 'order_contact_overdue',
            'name' => 'Minął umówiony kontakt',
            'label' => 'kontakt',
            'params' => ['days' => 1],
        ],
        [
            'type' => AlertConditionType::ORDER_MISSING_DRAWINGS,
            'code' => 'order_missing_drawings',
            'name' => 'Przyjęte zlecenie bez kompletu rysunków',
            'label' => 'brak rysunków',
            'params' => ['statuses' => ['ZLECENIE']],
        ],
        [
            'type' => AlertConditionType::ORDER_NO_PAYMENT,
            'code' => 'order_no_payment',
            'name' => 'Produkcja bez wpłaty',
            'label' => 'bez wpłaty',
            'params' => ['statuses' => ['PRODUKCJA']],
        ],
        [
            'type' => AlertConditionType::ORDER_ON_HOLD,
            'code' => 'order_on_hold',
            'name' => 'Zlecenie wstrzymane',
            'label' => 'wstrzymane',
            'params' => [],
        ],
        [
            'type' => AlertConditionType::ORDER_OPEN_CLAIM,
            'code' => 'order_open_claim',
            'name' => 'Otwarta reklamacja',
            'label' => 'reklamacja',
            'params' => [],
        ],
        // Trzy reguly dopisane 25.09 na prosbe Marcina. Progi to propozycja
        // do przejrzenia na `/alerts`, nie ustalenie.
        [
            'type' => AlertConditionType::ORDER_OVER_CREDIT_LIMIT,
            'code' => 'order_over_credit_limit',
            'name' => 'Kontrahent ponad limitem kupieckim',
            'label' => 'ponad limitem',
            'params' => ['statuses' => ['ZLECENIE', 'PRODUKCJA']],
        ],
        [
            'type' => AlertConditionType::ORDER_STUCK,
            'code' => 'order_stuck',
            'name' => 'Zlecenie za długo w statusie',
            'label' => 'stoi',
            'params' => ['statuses' => ['ZLECENIE'], 'days' => 7],
        ],
        [
            'type' => AlertConditionType::ORDER_UNPRICED,
            'code' => 'order_unpriced',
            'name' => 'Wycena bez cen',
            'label' => 'bez wyceny',
            'params' => ['statuses' => ['DO_WYCENY']],
        ],
        [
            'type' => AlertConditionType::STOCK_BELOW_MINIMUM,
            'code' => 'stock_below_minimum',
            'name' => 'Stan magazynowy poniżej minimum',
            'label' => 'poniżej minimum',
            'params' => [],
        ],
        [
            'type' => AlertConditionType::TEMPERING_BATCH_LATE,
            'code' => 'tempering_batch_late',
            'name' => 'Partia nie wróciła z pieca na czas',
            'label' => 'partia spóźniona',
            'params' => ['days' => 1],
        ],
    ];

    public function run(): void
    {
        $catalog = new ConditionCatalog();
        $position = 0;

        foreach (self::RULES as $definition) {
            $position += 10;
            $condition = $catalog->find($definition['type']->value);

            if ($condition === null) {
                continue;
            }

            if (AlertRule::query()->where('code', $definition['code'])->exists()) {
                continue;
            }

            AlertRule::query()->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                // Modul i kategoria ida z typu warunku — alert o zleceniu
                // nalezy do Zlecen niezaleznie od tego, kto go zakladal.
                'module' => $condition->module(),
                'category' => $condition->category()->value,
                'condition' => $catalog->normalize($condition, $definition['params']),
                'label' => $definition['label'],
                // Pusty kolor znaczy „domyslny kolor alertu" (--m-alert).
                // Wpisanie tu szesnastki rozjechaloby sie z paleta OKLCH.
                'color' => null,
                'position' => $position,
                'is_active' => true,
            ]);
        }
    }
}
