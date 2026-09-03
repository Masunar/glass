<?php

declare(strict_types=1);

namespace App\Dictionaries;

use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Vehicle;
use App\Models\Process;
use App\Models\Location;
use App\Enum\Permission;
use App\Models\InvoiceType;
use App\Models\Workstation;
use App\Models\CashRegister;
use App\Models\PriceSection;
use App\Enum\PaymentChannel;

/**
 * Rejestr słowników prostych — tych, których cała treść to lista
 * wierszy o stałym zestawie pól.
 *
 * Stary system miał piętnaście zakładek słowników, z których każda była
 * osobnym ekranem o własnym układzie. Tutaj ekran jest jeden, a różnicę
 * niesie definicja. Zysk nie jest kosmetyczny: dodanie kolumny „waga”
 * do rodzajów szkła było tam zmianą w kontrolerze, szablonie i modelu.
 *
 * Świadomie poza rejestrem zostają słowniki, które mają własne ekrany,
 * bo ich pozycje nie są płaskimi wierszami:
 *  - rodzaje i typy szkła, okuć i innych → kartoteka produktów (Cennik),
 *  - schematy formatek i zestawy okuć → własne edytory,
 *  - parametry ogólne → ekran Parametry (typowane, wersjonowane).
 */
final readonly class DictionaryRegistry
{
    /**
     * @return list<DictionaryDefinition>
     */
    public function all(): array
    {
        return [
            $this->locations(),
            $this->priceSections(),
            $this->invoiceTypes(),
            $this->cashRegisters(),
            $this->vehicles(),
            $this->workstations(),
            $this->processes(),
        ];
    }

    public function find(string $slug): ?DictionaryDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->slug === $slug) {
                return $definition;
            }
        }

        return null;
    }

    private function locations(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'locations',
            label: 'Lokalizacje',
            model: Location::class,
            fields: [
                new Field('name', 'Nazwa', required: true, max: 100),
                new Field('short_name', 'Skrót', max: 20),
                new Field('address_street', 'Ulica', max: 150, inList: false),
                new Field('address_house', 'Nr', max: 20, inList: false),
                new Field('address_postal_code', 'Kod', max: 15, inList: false),
                new Field('address_city', 'Miasto', max: 100),
                new Field('phone', 'Telefon', max: 30),
                new Field('email', 'E-mail', max: 150, extraRules: ['email']),
                new Field('is_production', 'Produkcja', FieldType::BOOLEAN),
                new Field('is_pickup_point', 'Odbiór', FieldType::BOOLEAN),
                new Field('is_default', 'Domyślna', FieldType::BOOLEAN, inList: false),
                new Field('is_active', 'Aktywna', FieldType::BOOLEAN),
            ],
            permission: Permission::LOCATIONS,
            note: 'Punkt firmy. Flagi „Produkcja” i „Odbiór” sterują tym, '
                . 'gdzie można zaplanować pracę i gdzie klient odbierze zlecenie.',
        );
    }

    private function priceSections(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'price-sections',
            label: 'Sekcje cenowe',
            model: PriceSection::class,
            fields: [
                new Field(
                    'section',
                    'Sekcja',
                    FieldType::SELECT,
                    required: true,
                    options: self::enumOptions(Section::cases(), [
                        'glass' => 'Szkło',
                        'fittings' => 'Okucia',
                        'services' => 'Usługi',
                        'other' => 'Inne',
                    ]),
                ),
                new Field('name', 'Nazwa', required: true, max: 60),
                new Field('is_default', 'Domyślna', FieldType::BOOLEAN),
                new Field('is_active', 'Aktywna', FieldType::BOOLEAN),
            ],
            permission: Permission::PRICE_LIST,
            uniqueWithin: ['section'],
            defaultScope: 'section',
            note: 'Poziom cenowy w obrębie sekcji. Limity rabatowe ról edytuje '
                . 'się przy samej sekcji — w starym systemie były kolumnami, '
                . 'więc czwarta rola sprzedażowa wymagałaby zmiany tabeli.',
        );
    }

    private function invoiceTypes(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'invoice-types',
            label: 'Typy faktur',
            model: InvoiceType::class,
            fields: [
                new Field('name', 'Nazwa', required: true, max: 60),
                new Field('vat_rate', 'VAT %', FieldType::INTEGER, required: true, max: 100),
                new Field('is_default', 'Domyślny', FieldType::BOOLEAN),
                new Field('is_active', 'Aktywny', FieldType::BOOLEAN),
            ],
        );
    }

    private function cashRegisters(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'cash-registers',
            label: 'Kasy',
            model: CashRegister::class,
            fields: [
                new Field('name', 'Nazwa', required: true, max: 60),
                new Field(
                    'channel',
                    'Kanał',
                    FieldType::SELECT,
                    required: true,
                    options: self::enumOptions(PaymentChannel::cases(), [
                        'cash' => 'Gotówka',
                        'transfer' => 'Przelew',
                    ]),
                ),
                new Field('location_id', 'Lokalizacja', FieldType::REFERENCE, source: 'locations'),
                new Field('user_id', 'Osoba', FieldType::REFERENCE, source: 'users'),
                new Field('default_currency', 'Waluta', required: true, max: 3),
                new Field('is_active', 'Aktywna', FieldType::BOOLEAN),
            ],
            eagerLoad: ['location', 'user'],
            note: 'Odpowiednik starych „Typów wpłat”, ale rozbity na trzy wymiary: '
                . 'kasę, kanał i walutę. W jednej liście nie dało się zapytać '
                . 'ani o wszystkie wpłaty gotówkowe, ani o wszystko w euro.',
        );
    }

    private function vehicles(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'vehicles',
            label: 'Samochody',
            model: Vehicle::class,
            fields: [
                new Field('name', 'Nazwa', required: true, max: 60),
                new Field('short_name', 'Skrót', max: 10),
                new Field('payload_kg', 'Ładowność (kg)', FieldType::INTEGER, required: true, max: 40000),
                new Field('location_id', 'Lokalizacja', FieldType::REFERENCE, source: 'locations'),
                new Field('crew_slots', 'Obsada', FieldType::INTEGER, max: 9),
                new Field('is_active', 'Aktywny', FieldType::BOOLEAN),
            ],
            eagerLoad: ['location'],
            note: 'Ładowność domyka łańcuch gęstość szkła → waga formatki → waga '
                . 'zlecenia → dobór auta. Bez wagi formatki dobór odbywał się na oko.',
        );
    }

    private function workstations(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'workstations',
            label: 'Stanowiska',
            model: Workstation::class,
            fields: [
                new Field('name', 'Nazwa', required: true, max: 100),
                new Field('location_id', 'Lokalizacja', FieldType::REFERENCE, source: 'locations'),
                new Field(
                    'daily_capacity',
                    'Przerób dzienny',
                    FieldType::INTEGER,
                    max: 65535,
                    hint: 'Liczba jednostek na dobę; puste = brak limitu w planowaniu.',
                ),
                new Field('is_active', 'Aktywne', FieldType::BOOLEAN),
            ],
            eagerLoad: ['location'],
        );
    }

    private function processes(): DictionaryDefinition
    {
        return new DictionaryDefinition(
            slug: 'processes',
            label: 'Procesy',
            model: Process::class,
            fields: [
                new Field('code', 'Kod', required: true, max: 2),
                new Field('name', 'Nazwa', required: true, max: 60),
                new Field('workstation_id', 'Stanowisko', FieldType::REFERENCE, source: 'workstations'),
                new Field(
                    'unit',
                    'Jednostka',
                    FieldType::SELECT,
                    required: true,
                    options: self::enumOptions(Unit::cases(), [
                        'm2' => 'm²',
                        'mb' => 'mb',
                        'pcs' => 'szt.',
                    ]),
                ),
                new Field(
                    'setup_minutes',
                    'Przygotowanie (min)',
                    FieldType::INTEGER,
                    max: 100000,
                    inList: false,
                    hint: 'Czas niezależny od wielkości partii.',
                ),
                new Field(
                    'unit_minutes',
                    'Na jednostkę (min)',
                    FieldType::INTEGER,
                    max: 100000,
                    inList: false,
                    hint: 'Stary system miał jedną liczbę dni niezależną od ilości, '
                        . 'więc 1 formatka i 100 sztuk zajmowały tyle samo.',
                ),
                new Field('duration_days', 'Czas (dni)', FieldType::INTEGER, max: 365),
                new Field('buffer_days', 'Bufor (dni)', FieldType::INTEGER, max: 365, inList: false),
                new Field('is_subcontracted', 'Podzlecane', FieldType::BOOLEAN),
                new Field(
                    'requires_parameter',
                    'Z parametrem',
                    FieldType::BOOLEAN,
                    inList: false,
                    hint: 'Proces niosący wartość: kod RAL, faza, rodzaj folii.',
                ),
                new Field('default_order', 'Kolejność', FieldType::INTEGER, max: 999, inList: false),
                new Field('is_active', 'Aktywny', FieldType::BOOLEAN),
            ],
            eagerLoad: ['workstation'],
            orderColumn: 'default_order',
            note: 'Proces jest pozycją słownika, nie kolumną w kodzie — siatka '
                . 'produkcji generuje kolumny z tej listy.',
        );
    }

    /**
     * @param list<\BackedEnum> $cases
     * @param array<string, string> $labels
     * @return list<array{value: string, label: string}>
     */
    private static function enumOptions(array $cases, array $labels): array
    {
        $options = [];

        foreach ($cases as $case) {
            $value = (string) $case->value;
            $options[] = ['value' => $value, 'label' => $labels[$value] ?? $value];
        }

        return $options;
    }
}
