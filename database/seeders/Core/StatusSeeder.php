<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\Status;
use App\Enum\StatusDomain;
use Salvon\Database\Seeder;
use App\Models\StatusTransition;

/**
 * Jeden katalog statusów dla wszystkich modułów.
 *
 * Dwie decyzje odróżniające go od stanu zastanego:
 *
 * 1. „Uwaga" i „Reklamacja" NIE są statusami, tylko flagami na zleceniu.
 *    Jako statusy gubiły informację, gdzie zlecenie faktycznie stoi,
 *    i wyrzucały rozliczone zlecenie z powrotem do obiegu.
 *
 * 2. „Anulowane" rozdzielone na OFERTA_ODRZUCONA i ANULOWANE. Stary
 *    system trzymał pod jedną nazwą naturalny koszt sprzedaży (oferta,
 *    której klient nie przyjął — około 4 500 rekordów) i realny problem
 *    (zlecenie odwołane po przyjęciu). Bez rozdzielenia żaden wskaźnik
 *    oparty na anulowaniach niczego nie mierzy.
 */
class StatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->statuses() as $domain => $statuses) {
            foreach ($statuses as $status) {
                Status::query()->firstOrCreate(
                    ['domain' => $domain, 'code' => $status['code']],
                    [...$status, 'domain' => $domain],
                );
            }
        }

        $this->orderTransitions();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function statuses(): array
    {
        return [
            StatusDomain::ORDER->value => [
                ['code' => 'DO_WYCENY', 'name' => 'Do wyceny', 'position' => 10, 'is_default' => true],
                ['code' => 'ZLECENIE', 'name' => 'Zlecenie', 'position' => 20],
                ['code' => 'PRODUKCJA', 'name' => 'Produkcja', 'position' => 30],
                ['code' => 'GOTOWE', 'name' => 'Gotowe', 'position' => 40],
                ['code' => 'DOSTAWA', 'name' => 'Dostawa', 'position' => 50],
                ['code' => 'ODBIOR', 'name' => 'Odbiór', 'position' => 51],
                ['code' => 'MONTAZ', 'name' => 'Montaż', 'position' => 52],
                ['code' => 'NIEROZLICZONE', 'name' => 'Nierozliczone', 'position' => 60],
                ['code' => 'ROZLICZONE', 'name' => 'Rozliczone', 'position' => 70],
                ['code' => 'ARCHIWUM', 'name' => 'Historia', 'position' => 80, 'is_final' => true],
                ['code' => 'OFERTA_ODRZUCONA', 'name' => 'Oferta odrzucona', 'position' => 90, 'is_final' => true],
                ['code' => 'ANULOWANE', 'name' => 'Anulowane', 'position' => 91, 'is_final' => true],
            ],
            StatusDomain::MEASUREMENT->value => [
                ['code' => 'UMOWIONY', 'name' => 'Umówiony', 'position' => 10, 'is_default' => true],
                ['code' => 'WYKONANY', 'name' => 'Wykonany', 'position' => 20],
                ['code' => 'ANULOWANY', 'name' => 'Anulowany', 'position' => 30, 'is_final' => true],
            ],
            StatusDomain::COMPLAINT->value => [
                ['code' => 'ZGLOSZONA', 'name' => 'Zgłoszona', 'position' => 10, 'is_default' => true],
                ['code' => 'W_ANALIZIE', 'name' => 'W analizie', 'position' => 20],
                ['code' => 'UZNANA', 'name' => 'Uznana', 'position' => 30],
                ['code' => 'ODRZUCONA', 'name' => 'Odrzucona', 'position' => 40, 'is_final' => true],
                ['code' => 'W_REALIZACJI', 'name' => 'W realizacji', 'position' => 50],
                ['code' => 'ZAMKNIETA', 'name' => 'Zamknięta', 'position' => 60, 'is_final' => true],
            ],
            StatusDomain::TEMPERING->value => [
                ['code' => 'W_KOLEJCE', 'name' => 'W kolejce', 'position' => 10, 'is_default' => true],
                ['code' => 'WYSLANA', 'name' => 'Wysłana', 'position' => 20],
                ['code' => 'WROCILA', 'name' => 'Wróciła', 'position' => 30],
                ['code' => 'DO_POPRAWKI', 'name' => 'Do poprawki', 'position' => 40],
                ['code' => 'STLUCZKA', 'name' => 'Stłuczka', 'position' => 50, 'is_final' => true],
                ['code' => 'BRAK', 'name' => 'Brak', 'position' => 60, 'is_final' => true],
            ],
            StatusDomain::COMMISSION->value => [
                ['code' => 'PROGNOZOWANA', 'name' => 'Prognozowana', 'position' => 10, 'is_default' => true],
                ['code' => 'NALEZNA', 'name' => 'Należna', 'position' => 20],
                ['code' => 'WYPLACONA', 'name' => 'Wypłacona', 'position' => 30, 'is_final' => true],
                ['code' => 'ANULOWANA', 'name' => 'Anulowana', 'position' => 40, 'is_final' => true],
            ],
        ];
    }

    /**
     * Ścieżka zlecenia. Warunki są deklaratywne i niosą własny komunikat,
     * żeby zablokowany przycisk mówił, czego brakuje i gdzie to uzupełnić.
     */
    private function orderTransitions(): void
    {
        $transitions = [
            ['DO_WYCENY', 'ZLECENIE', 'Przyjmij zlecenie', [
                ['rule' => 'has_enabled_list', 'message' => 'Zlecenie nie ma żadnej włączonej listy.'],
                ['rule' => 'total_above_zero', 'message' => 'Wartość zlecenia wynosi zero.'],
                ['rule' => 'customer_complete', 'message' => 'Dane kontrahenta są niekompletne.'],
                ['rule' => 'invoice_data_complete', 'message' => 'Brakuje danych do faktury.'],
            ]],
            ['ZLECENIE', 'PRODUKCJA', 'Przekaż do produkcji', [
                ['rule' => 'all_drawings_added', 'message' => 'Nie zaznaczono, że wszystkie rysunki są dodane.'],
                ['rule' => 'no_list_on_hold', 'message' => 'Co najmniej jedna lista jest wstrzymana.'],
                ['rule' => 'prepayment_or_credit_limit', 'message' => 'Brak zaliczki, a kontrahent nie mieści się w limicie kredytowym.'],
            ]],
            ['PRODUKCJA', 'GOTOWE', 'Oznacz jako gotowe', [
                ['rule' => 'all_production_tasks_done', 'message' => 'Nie wszystkie etapy produkcji są wykonane.'],
            ]],
            ['GOTOWE', 'DOSTAWA', 'Skieruj do dostawy', [
                ['rule' => 'handover_method_is', 'value' => 'delivery', 'message' => 'Zlecenie nie jest oznaczone jako dowóz.'],
            ]],
            ['GOTOWE', 'ODBIOR', 'Skieruj do odbioru', [
                ['rule' => 'handover_method_is', 'value' => 'pickup', 'message' => 'Zlecenie nie jest oznaczone jako odbiór własny.'],
                ['rule' => 'pickup_point_set', 'message' => 'Nie wskazano punktu odbioru.'],
            ]],
            ['GOTOWE', 'MONTAZ', 'Skieruj do montażu', [
                ['rule' => 'handover_method_is', 'value' => 'installation', 'message' => 'Zlecenie nie jest oznaczone jako montaż.'],
            ]],
            ['DOSTAWA', 'NIEROZLICZONE', 'Potwierdź wydanie', []],
            ['ODBIOR', 'NIEROZLICZONE', 'Potwierdź wydanie', []],
            ['MONTAZ', 'NIEROZLICZONE', 'Potwierdź montaż', []],
            ['NIEROZLICZONE', 'ROZLICZONE', 'Rozlicz', [
                ['rule' => 'balance_is_zero', 'message' => 'Zlecenie ma niezerowe saldo.'],
            ]],
            ['ROZLICZONE', 'ARCHIWUM', 'Zamknij', [
                ['rule' => 'no_open_complaint', 'message' => 'Zlecenie ma otwartą reklamację.'],
            ]],
            ['DO_WYCENY', 'OFERTA_ODRZUCONA', 'Klient nie przyjął oferty', [
                ['rule' => 'rejection_reason_set', 'message' => 'Podaj powód nieprzyjęcia oferty.'],
            ]],
        ];

        // anulowanie dostepne z kazdego statusu, ktory nie jest koncowy
        foreach (['DO_WYCENY', 'ZLECENIE', 'PRODUKCJA', 'GOTOWE', 'DOSTAWA', 'ODBIOR', 'MONTAZ', 'NIEROZLICZONE'] as $from) {
            $transitions[] = [$from, 'ANULOWANE', 'Anuluj zlecenie', [
                ['rule' => 'cancellation_reason_set', 'message' => 'Podaj powód anulowania.'],
            ]];
        }

        $position = 0;

        foreach ($transitions as [$from, $to, $label, $conditions]) {
            $fromStatus = Status::findByCode(StatusDomain::ORDER, $from);
            $toStatus = Status::findByCode(StatusDomain::ORDER, $to);

            if ($fromStatus === null || $toStatus === null) {
                continue;
            }

            StatusTransition::query()->firstOrCreate(
                ['from_status_id' => $fromStatus->id, 'to_status_id' => $toStatus->id],
                [
                    'domain' => StatusDomain::ORDER->value,
                    'conditions' => $conditions === [] ? null : $conditions,
                    'button_label' => $label,
                    'position' => $position += 10,
                ],
            );
        }
    }
}
