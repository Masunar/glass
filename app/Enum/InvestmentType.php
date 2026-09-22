<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rodzaj obiektu, w którym prowadzona jest inwestycja.
 *
 * Nie jest to opis budynku dla ładnego wydruku — to jedyna rzecz,
 * która wybiera limit powierzchni z art. 41 ust. 12b ustawy o VAT,
 * a więc decyduje, jaka część zlecenia idzie na stawkę obniżoną.
 *
 * Brak wartości (`null` na zleceniu) znaczy „nie dotyczy": zwykła
 * sprzedaż, bez budownictwa objętego społecznym programem
 * mieszkaniowym, cała kwota na stawce z typu faktury.
 */
enum InvestmentType: string
{
    case HOUSE = 'house';
    case FLAT = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::HOUSE => 'Dom jednorodzinny',
            self::FLAT => 'Lokal mieszkalny',
        };
    }

    /** Klucz parametru z limitem powierzchni dla tego rodzaju obiektu. */
    public function limitParameter(): string
    {
        return match ($this) {
            self::HOUSE => 'vat_limit_m2_house',
            self::FLAT => 'vat_limit_m2_flat',
        };
    }
}
