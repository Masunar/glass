<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Osoba prywatna czy firma.
 *
 * Pole jawne, a nie wyprowadzane z obecności NIP-u. Od typu zależy
 * walidacja, dokument sprzedaży i obowiązki RODO — rozpoznawanie
 * po pustym polu psuje wydruki i obowiązki fakturowe.
 */
enum ContractorType: string
{
    case PERSON = 'person';
    case COMPANY = 'company';
}
