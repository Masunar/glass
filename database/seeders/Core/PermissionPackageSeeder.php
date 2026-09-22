<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\Role;
use Salvon\Database\Seeder;
use App\Support\AccessRegistry;
use App\Models\PermissionPackage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Paczki startowe — **propozycja z dokumentacji, nie stan zastany**.
 *
 * Siedem ról powstało, zanim uprawnienia zaczęły cokolwiek znaczyć,
 * więc wszystkie są puste. Po wpięciu `*.access` w listwę nawigacji
 * pusta rola przestaje być niegroźna: jej użytkownik nie zobaczy nic.
 * Ten zasiew daje im punkt wyjścia.
 *
 * Źródłem jest macierz z `docs/60-uzytkownicy.md` §5.2 — ta sama, którą
 * Marcin ma zweryfikować wiersz po wierszu. **Nic tu nie jest wymyślone
 * ponad nią**, a tam, gdzie macierz mówi więcej, niż model dziś potrafi,
 * nie nadajemy nic:
 *
 * - **Pracownik zostaje pusty.** Macierz daje mu „zlecenia własne", a
 *   zakresu danych (wszystkie / lokalizacja / własne) w modelu nie ma.
 *   Nadanie mu `orders.list` otworzyłoby wszystkie zlecenia z cenami —
 *   czyli więcej, niż mówi dokument. Brak zakresu nie jest zgodą.
 * - **Handlowiec i Starszy handlowiec dostają to samo.** Różnią się
 *   akceptacją rabatów i podglądem marży, a na żadną z tych rzeczy nie
 *   ma dziś uprawnienia. Kiedy powstanie, rozejdą się same.
 *
 * Zasiew jest **zachowawczy, nie uzgadniający** — odwrotnie niż
 * `PermissionSeeder`. Paczka, która już istnieje, nie jest ruszana, a
 * rola dostaje przypięcie tylko wtedy, gdy **nigdy nie była
 * konfigurowana** (zero uprawnień i zero paczek). Inaczej każde
 * wdrożenie cofałoby ręczne ustawienia administratora.
 */
class PermissionPackageSeeder extends Seeder
{
    /** @var array<string, array{description: string, permissions: list<string>}> */
    private const PACKAGES = [
        'Zlecenia — obsługa' => [
            'description' => 'Prowadzenie zleceń, wycen i ofert oraz kartoteka kontrahentów. Cennik tylko do odczytu.',
            'permissions' => [
                'zlec.access',
                'orders.list', 'orders.create', 'orders.update',
                'offers.list', 'offers.create', 'offers.update',
                'contractors.list', 'contractors.create', 'contractors.update',
                'price_list.list',
            ],
        ],
        'Zlecenia — podgląd' => [
            'description' => 'Wgląd w zlecenia, oferty i kontrahentów bez prawa zmiany.',
            'permissions' => [
                'zlec.access',
                'orders.list', 'offers.list', 'contractors.list', 'price_list.list',
            ],
        ],
        'Produkcja — obsługa' => [
            'description' => 'Kolejka produkcyjna i hartownia: zakładanie wsadów, odhaczanie etapów.',
            'permissions' => [
                'prod.access',
                'production.list', 'production.update',
                'tempering.list', 'tempering.create', 'tempering.update', 'tempering.delete',
            ],
        ],
        'Produkcja — podgląd' => [
            'description' => 'Wgląd w kolejkę produkcyjną i hartownię bez prawa zmiany.',
            'permissions' => ['prod.access', 'production.list', 'tempering.list'],
        ],
        'Magazyn — obsługa' => [
            'description' => 'Stany, przyjęcia i wydania magazynowe.',
            'permissions' => [
                'mag.access',
                'warehouse.list', 'warehouse.create', 'warehouse.update', 'warehouse.delete',
            ],
        ],
        'Magazyn — podgląd' => [
            'description' => 'Wgląd w stany magazynowe bez prawa zmiany.',
            'permissions' => ['mag.access', 'warehouse.list'],
        ],
        'Księgowość' => [
            'description' => 'Rozliczenia: wgląd w zlecenia i oferty, pełna kartoteka kontrahentów, podgląd magazynu.',
            'permissions' => [
                'ksie.access', 'zlec.access', 'mag.access',
                'orders.list', 'offers.list',
                'contractors.list', 'contractors.create', 'contractors.update',
                'price_list.list', 'warehouse.list',
            ],
        ],
        'Konfiguracja systemu' => [
            'description' => 'Słowniki, kartoteka produktów, parametry wyceny i cennik.',
            'permissions' => [
                'adm.access',
                'dictionaries.list', 'dictionaries.create', 'dictionaries.update', 'dictionaries.delete',
                'products.list', 'products.create', 'products.update', 'products.delete',
                'parameters.list', 'parameters.update',
                'price_list.list', 'price_list.update',
            ],
        ],
        'Konta i uprawnienia' => [
            'description' => 'Użytkownicy, role i paczki uprawnień.',
            'permissions' => [
                'adm.access',
                'users.list', 'users.read', 'users.create', 'users.update', 'users.delete', 'users.restore',
                'roles.list', 'roles.create', 'roles.update', 'roles.delete',
                'permissions.list', 'permissions.update',
            ],
        ],
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PACKAGES = [
        RoleSeeder::SENIOR_SALES => ['Zlecenia — obsługa', 'Produkcja — podgląd', 'Magazyn — podgląd'],
        RoleSeeder::SALES => ['Zlecenia — obsługa', 'Produkcja — podgląd', 'Magazyn — podgląd'],
        'Grafik' => ['Zlecenia — podgląd'],
        'Księgowa' => ['Księgowość'],
        'Szef produkcji' => ['Zlecenia — podgląd', 'Produkcja — obsługa', 'Magazyn — obsługa'],
        // „Pracownik" swiadomie bez paczki — powod w docbloku klasy.
    ];

    public function run(): void
    {
        $ids = $this->packages();
        $this->roles($ids);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Paczki — zakładane raz, potem nietykalne.
     *
     * @return array<string, int>
     */
    private function packages(): array
    {
        /** @var array<string, int> $permissionIds */
        $permissionIds = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->all();

        $known = AccessRegistry::names();
        $ids = [];

        foreach (self::PACKAGES as $name => $definition) {
            /** @var PermissionPackage|null $existing */
            $existing = PermissionPackage::query()->where('name', $name)->first();

            if ($existing !== null) {
                // Istniejaca paczka moze byc juz poprawiona recznie —
                // zasiew nie ma prawa cofac tamtej decyzji.
                $ids[$name] = (int) $existing->getKey();

                continue;
            }

            /** @var PermissionPackage $package */
            $package = PermissionPackage::query()->create([
                'name' => $name,
                'description' => $definition['description'],
            ]);

            $wanted = array_values(array_intersect($definition['permissions'], $known));
            $package->permissions()->sync(array_values(array_filter(
                array_map(static fn(string $n): ?int => $permissionIds[$n] ?? null, $wanted),
            )));

            $ids[$name] = (int) $package->getKey();
        }

        return $ids;
    }

    /**
     * Przypięcie paczek do ról, które nikt jeszcze nie konfigurował.
     *
     * @param array<string, int> $ids
     */
    private function roles(array $ids): void
    {
        foreach (self::ROLE_PACKAGES as $roleName => $names) {
            /** @var Role|null $role */
            $role = Role::query()
                ->withCount(['permissions', 'packages'])
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                continue;
            }

            // Rola raz tknieta przez czlowieka zostaje jego. Zasiew
            // uzupelnia puste miejsce, nie poprawia cudzej pracy.
            $touched = (int) ($role->permissions_count ?? 0) > 0
                || (int) ($role->packages_count ?? 0) > 0;

            if ($touched) {
                continue;
            }

            $attach = array_values(array_filter(
                array_map(static fn(string $n): ?int => $ids[$n] ?? null, $names),
            ));

            $role->packages()->sync($attach);
        }
    }
}
