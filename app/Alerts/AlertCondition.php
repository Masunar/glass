<?php

declare(strict_types=1);

namespace App\Alerts;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;

/**
 * Jeden typ warunku alertu.
 *
 * Warunek **buduje zapytanie**, a nie ogląda wczytany model. Czerwony
 * licznik przy zakładce ma mówić o całej bazie, nie o dwustu wierszach
 * pokazanych na ekranie, więc warunek musi umieć odpowiedzieć jednym
 * zapytaniem dla wszystkich zleceń naraz.
 */
interface AlertCondition
{
    public function type(): AlertConditionType;

    public function label(): string;

    public function category(): AlertCategory;

    /** Moduł, w którym alert jest widoczny — klucz z `AccessRegistry::MODULES`. */
    public function module(): string;

    /** Klasa encji, której dotyczy alert (`alertable_type`). */
    public function alertable(): string;

    /**
     * Zasób, którego alert dotyczy — `orders`, `warehouse`, `tempering`.
     *
     * Z niego **wyprowadzane** są oba potrzebne uprawnienia: `list` do
     * zobaczenia alertu i `update` do odhaczenia go. Wpisane osobno
     * rozjechałyby się przy pierwszej zmianie, a byłby to rozjazd bez
     * objawów — alert widoczny dla kogoś, kto samej rzeczy nie widzi.
     *
     * Pośrednik od dostępu do modułu tego nie wyprowadzi, bo zależy to
     * od **wiersza**, a nie od trasy; sprawdza to usługa, która wiersz
     * już zna.
     */
    public function resource(): string;

    /**
     * Podpisy encji: identyfikator => co pokazać i dokąd to prowadzi.
     *
     * Pasmo alertów na pulpicie musi umieć nazwać każdą rzecz, której
     * alert dotyczy — nie tylko zlecenie. Wiedza o tym, jak nazywa się
     * encja i gdzie leży jej ekran, siedzi przy warunku, bo tam już
     * stoi zapytanie o tę tabelę.
     *
     * @param list<int> $ids
     * @return array<int, array{label: string, path: string|null}>
     */
    public function subjects(array $ids): array;

    /**
     * Parametry typu: co administrator ustawia na ekranie reguł.
     *
     * @return list<array{key: string, label: string, type: string, default: int|string|list<string>, hint: string}>
     */
    public function parameters(): array;

    /**
     * Encje spełniające warunek: identyfikator => wartość, która alert
     * wywołała (np. liczba dni po terminie). `null` znaczy „warunek
     * spełniony, ale nie ma czego wpisać w liczbę".
     *
     * @param array<string, mixed> $params
     * @return array<int, string|null>
     */
    public function find(Carbon $day, array $params): array;
}
