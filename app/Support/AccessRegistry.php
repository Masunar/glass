<?php

declare(strict_types=1);

namespace App\Support;

use App\Enum\Permission;
use Salvon\Enum\SubPermission;

/**
 * Rejestr modułów, stron i uprawnień — **spis pochodzi z kodu**.
 *
 * Powiązanie „strona ↔ uprawnienie" żyje w bazie i administrator może
 * je zmienić bez wdrożenia (U-01). Ale **co w ogóle istnieje**, wynika
 * z kodu: trasy są w `app-router.ts`, uprawnienia w `App\Enum\Permission`,
 * a `protect()` w kontrolerach. Baza przechowuje przypisania, nie katalog.
 *
 * Bez tego rozdziału dryf jest nieunikniony: we wzorcu, który był
 * inspiracją, uzbierało się **75 uprawnień bez przypisanej strony**.
 * Nasze mają wywalać CI (`AccessCoverageTest`), a nie lądować w kafelku
 * „nie udało się powiązać".
 *
 * ⚠️ **Czterech uprawnień z enuma nie sprawdza żaden kontroler**:
 * `LOCATIONS`, `STATUSES`, `ALERTS`, `AUDIT`. Było sześć — `ROLES`
 * i `PERMISSIONS` dostały ekran i przeszły do działających.
 * Stoją tu jako `PLANNED` razem z powodem — nie po to, żeby je ukryć,
 * tylko żeby nikt nie musiał zgadywać, czy to przeoczenie, czy plan.
 */
final readonly class AccessRegistry
{
    /**
     * Dostęp do modułu — poziom nad dostępem do strony (U-04).
     *
     * Nie ma go w `Salvon\Enum\SubPermission`, bo to plik frameworka,
     * a nasze poprawki w `salvon/` znikają przy jego aktualizacji.
     */
    public const ACCESS = 'access';

    /** Uprawnienie działające — ma kontroler, który je sprawdza. */
    public const ACTIVE = 'active';

    /** Uprawnienie w enumie, którego **żaden kontroler nie sprawdza**. */
    public const PLANNED = 'planned';

    /** Moduły z listwy nawigacji — klucz musi zgadzać się z `modules.ts`. */
    public const MODULES = [
        'zlec' => 'Zlecenia',
        'prod' => 'Produkcja',
        'mag' => 'Magazyn',
        'ksie' => 'Księgowość',
        'rap' => 'Raporty',
        'adm' => 'Administracja',
    ];

    /**
     * Strony aplikacji — odzwierciedlenie `app-router.ts`.
     *
     * `permission` to **domyślne** uprawnienie otwierające stronę. Po
     * zasianiu żyje w bazie i da się je zmienić z panelu; tutaj stoi po
     * to, żeby nowa trasa nie powstała bez przypisania.
     *
     * `null` znaczy stronę dostępną każdemu zalogowanemu — dziś tylko
     * pulpit.
     *
     * @var array<string, array{path: string, module: string|null, label: string, permission: string|null}>
     */
    public const PAGES = [
        'index' => ['path' => '/', 'module' => null, 'label' => 'Pulpit', 'permission' => null],

        'orders' => ['path' => '/orders', 'module' => 'zlec', 'label' => 'Zlecenia', 'permission' => 'orders.list'],
        'order_card' => ['path' => '/orders/:id', 'module' => 'zlec', 'label' => 'Karta zlecenia', 'permission' => 'orders.list'],
        'order_panes' => ['path' => '/orders/:id/formatki', 'module' => 'zlec', 'label' => 'Formatki', 'permission' => 'orders.list'],
        'order_drawings' => ['path' => '/orders/:id/rysunki', 'module' => 'zlec', 'label' => 'Rysunki', 'permission' => 'orders.list'],
        'order_payments' => ['path' => '/orders/:id/platnosci', 'module' => 'zlec', 'label' => 'Płatności zlecenia', 'permission' => 'orders.list'],
        'order_log' => ['path' => '/orders/:id/dziennik', 'module' => 'zlec', 'label' => 'Dziennik zlecenia', 'permission' => 'orders.list'],
        'order_offers' => ['path' => '/orders/:id/oferty', 'module' => 'zlec', 'label' => 'Oferty zlecenia', 'permission' => 'offers.list'],
        'offers' => ['path' => '/offers', 'module' => 'zlec', 'label' => 'Oferty', 'permission' => 'offers.list'],
        'contractors' => ['path' => '/contractors', 'module' => 'zlec', 'label' => 'Kontrahenci', 'permission' => 'contractors.list'],
        'price_list' => ['path' => '/price-list', 'module' => 'zlec', 'label' => 'Cennik', 'permission' => 'price_list.list'],

        'production' => ['path' => '/produkcja', 'module' => 'prod', 'label' => 'Produkcja', 'permission' => 'production.list'],
        'tempering' => ['path' => '/hartownia', 'module' => 'prod', 'label' => 'Hartownia', 'permission' => 'tempering.list'],

        'warehouse' => ['path' => '/magazyn', 'module' => 'mag', 'label' => 'Magazyn', 'permission' => 'warehouse.list'],

        'users' => ['path' => '/users', 'module' => 'adm', 'label' => 'Użytkownicy', 'permission' => 'users.list'],
        'access' => ['path' => '/access', 'module' => 'adm', 'label' => 'Role i uprawnienia', 'permission' => 'roles.list'],
        'access_role' => ['path' => '/access/roles/:id', 'module' => 'adm', 'label' => 'Konfiguracja roli', 'permission' => 'roles.list'],
        'dictionaries' => ['path' => '/dictionaries', 'module' => 'adm', 'label' => 'Słowniki', 'permission' => 'dictionaries.list'],
        'parameters' => ['path' => '/parameters', 'module' => 'adm', 'label' => 'Parametry wyceny', 'permission' => 'parameters.list'],
    ];

    /**
     * Uprawnienia: moduł, etykieta, poduprawnienia, strona i stan.
     *
     * `page` wskazuje stronę, przy której uprawnienie pokazuje się na
     * ekranie konfiguracji. `null` przy `PLANNED` jest w porządku —
     * ekranu jeszcze nie ma. `null` przy `ACTIVE` **nie jest** i wywala
     * test pokrycia.
     *
     * @var array<string, array{module: string, label: string, subs: list<string>, page: string|null, state: string, note: string}>
     */
    public const PERMISSIONS = [
        Permission::ORDERS->value => [
            'module' => 'zlec', 'label' => 'Zlecenia',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'orders', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::OFFERS->value => [
            'module' => 'zlec', 'label' => 'Oferty',
            'subs' => ['list', 'create', 'update'],
            'page' => 'offers', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::CONTRACTORS->value => [
            'module' => 'zlec', 'label' => 'Kontrahenci',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'contractors', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::PRICE_LIST->value => [
            'module' => 'zlec', 'label' => 'Cennik',
            'subs' => ['list', 'update'],
            'page' => 'price_list', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::PRODUCTION->value => [
            'module' => 'prod', 'label' => 'Produkcja',
            'subs' => ['list', 'update'],
            'page' => 'production', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::TEMPERING->value => [
            'module' => 'prod', 'label' => 'Hartownia',
            // `delete` chroni wyjecie formatki z partii
            // (`TemperingController::removeItem`).
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'tempering', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::WAREHOUSE->value => [
            'module' => 'mag', 'label' => 'Magazyn',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'warehouse', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::USERS->value => [
            'module' => 'adm', 'label' => 'Użytkownicy',
            // `read` i `restore` nie sa ozdoba: `Route::crud()` zaklada
            // trasy `GET /users/{id}` i `PUT /users/{id}/restore`,
            // a `ApiCrudController` chroni je automatycznie. Bez wpisu
            // uprawnienie nie powstawalo przy zasiewie, a spatie rzuca
            // wyjatkiem na nieistniejacym — czyli kazdy poza rola
            // nadrzedna dostawal blad zamiast odmowy.
            'subs' => ['list', 'read', 'create', 'update', 'delete', 'restore'],
            'page' => 'users', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::DICTIONARIES->value => [
            'module' => 'adm', 'label' => 'Słowniki',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'dictionaries', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::PARAMETERS->value => [
            'module' => 'adm', 'label' => 'Parametry wyceny',
            'subs' => ['list', 'update'],
            'page' => 'parameters', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::PRODUCTS->value => [
            'module' => 'adm', 'label' => 'Kartoteka produktów',
            'subs' => ['list', 'create', 'update', 'delete'],
            // Kartoteka nie ma wlasnego adresu — siedzi w Slownikach.
            'page' => 'dictionaries', 'state' => self::ACTIVE, 'note' => '',
        ],

        // --- Zaplanowane: w enumie, bez kontrolera --------------------

        Permission::ROLES->value => [
            'module' => 'adm', 'label' => 'Role',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => 'access', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::PERMISSIONS->value => [
            'module' => 'adm', 'label' => 'Uprawnienia i paczki',
            'subs' => ['list', 'update'],
            'page' => 'access', 'state' => self::ACTIVE, 'note' => '',
        ],
        Permission::AUDIT->value => [
            'module' => 'adm', 'label' => 'Dziennik zmian',
            'subs' => ['list'],
            'page' => null, 'state' => self::PLANNED,
            'note' => 'Dziennik jest dziś zakładką zlecenia; osobnego ekranu nie ma.',
        ],
        Permission::ALERTS->value => [
            'module' => 'adm', 'label' => 'Alerty',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => null, 'state' => self::PLANNED,
            'note' => 'Silnik alertów zaprojektowany w 99-model-danych.md, niezbudowany.',
        ],
        Permission::LOCATIONS->value => [
            'module' => 'adm', 'label' => 'Lokalizacje',
            'subs' => ['list', 'create', 'update', 'delete'],
            'page' => null, 'state' => self::PLANNED,
            'note' => 'Lokalizacje są dziś słownikiem pod uprawnieniem dictionaries.',
        ],
        Permission::STATUSES->value => [
            'module' => 'adm', 'label' => 'Statusy i przejścia',
            'subs' => ['list', 'update'],
            'page' => null, 'state' => self::PLANNED,
            'note' => 'Katalog statusów jest zaseedowany i nieedytowalny z aplikacji.',
        ],
    ];

    /**
     * Uprawnienia jako **zadeklarowany kontrakt**, nie surowa stała.
     *
     * Czytanie `self::PERMISSIONS` wprost daje analizie statycznej
     * literalne wartości dzisiejszej zawartości — a wtedy każdy test
     * pilnujący tego rejestru jest „zawsze prawdziwy" i wywala się na
     * `identical.alwaysFalse`. Test ma pilnować **reguły**, nie
     * dzisiejszego stanu, więc pytamy o typ zadeklarowany.
     *
     * `note` jest `string`, nie `string|null`: brak powodu to pusty
     * napis. Rozróżnianie „nie ma powodu" od „powód jest pusty" nie
     * niosło tu niczego, a kosztowało warunek w każdym miejscu użycia.
     *
     * @return array<string, array{module: string, label: string, subs: list<string>, page: string|null, state: string, note: string}>
     */
    public static function permissions(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Strony jako zadeklarowany kontrakt — z tego samego powodu.
     *
     * @return array<string, array{path: string, module: string|null, label: string, permission: string|null}>
     */
    public static function pages(): array
    {
        return self::PAGES;
    }

    /**
     * Wszystkie nazwy uprawnień do zasiania: `modul.access` plus
     * `zasob.poduprawnienie`.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (array_keys(self::MODULES) as $module) {
            $names[] = $module . '.' . self::ACCESS;
        }

        foreach (self::permissions() as $key => $definition) {
            foreach ($definition['subs'] as $sub) {
                $names[] = $key . '.' . $sub;
            }
        }

        return $names;
    }

    /** Moduł, do którego należy uprawnienie — dla kontroli spójności. */
    public static function moduleOf(string $permission): ?string
    {
        $resource = explode('.', $permission)[0];

        if (array_key_exists($resource, self::MODULES)) {
            return $resource;
        }

        return self::PERMISSIONS[$resource]['module'] ?? null;
    }

    /** Czy to uprawnienie otwierające moduł (`zlec.access`). */
    public static function isModuleAccess(string $permission): bool
    {
        return str_ends_with($permission, '.' . self::ACCESS)
            && array_key_exists(explode('.', $permission)[0], self::MODULES);
    }

    /**
     * Poduprawnienia znane frameworkowi plus nasze `access`.
     *
     * @return list<string>
     */
    public static function subs(): array
    {
        $subs = [self::ACCESS];

        foreach (SubPermission::cases() as $case) {
            if ($case === SubPermission::WILDCARD) {
                continue;
            }

            $subs[] = $case->value;
        }

        return $subs;
    }
}
