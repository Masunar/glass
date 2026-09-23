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
