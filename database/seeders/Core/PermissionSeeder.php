<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\AppPage;
use Salvon\Database\Seeder;
use App\Support\AccessRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Uprawnienia i strony — zasiew z rejestru.
 *
 * **To jest pierwsze uruchomienie mechanizmu, który leżał napisany od
 * początku.** `protect()` stoi w kontrolerach od pierwszego commita,
 * ale tabela uprawnień była pusta, więc przechodziła wyłącznie rola
 * nadrzędna przez `Gate::before`. Spatie rzuca wyjątkiem na
 * nieistniejącym uprawnieniu — czyli każdy użytkownik bez roli
 * nadrzędnej dostawał błąd, nie odmowę.
 *
 * Zasiew jest **uzgadniający, nie dopisujący**: uprawnienia i strony
 * spoza rejestru znikają. Inaczej po każdej zmianie nazwy zostawałby
 * martwy wiersz, którego nikt już nie sprawdza, a który dalej da się
 * nadać roli — i to jest dokładnie ta rodzina sierot, przed którą ten
 * rejestr ma bronić.
 *
 * Przypisania stron **nie nadpisujemy**, gdy już istnieje: administrator
 * mógł je świadomie zmienić (U-01), a seeder nie ma prawa cofać jego
 * decyzji przy każdym wdrożeniu.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->permissions();
        $this->pages();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): void
    {
        $wanted = AccessRegistry::names();

        foreach ($wanted as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        // Uprawnienie spoza rejestru nie ma czego chronic — zadna
        // metoda go nie sprawdza, a nadac je nadal mozna.
        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $wanted)
            ->delete();
    }

    private function pages(): void
    {
        /** @var array<string, int> $ids */
        $ids = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->all();

        $position = 0;

        foreach (AccessRegistry::PAGES as $code => $page) {
            $position += 10;

            /** @var AppPage|null $existing */
            $existing = AppPage::query()->where('code', $code)->first();

            $attributes = [
                'code' => $code,
                'path' => $page['path'],
                'module' => $page['module'],
                'label' => $page['label'],
                'position' => $position,
            ];

            if ($existing === null) {
                $permission = $page['permission'];

                AppPage::query()->create($attributes + [
                    'permission_id' => $permission === null ? null : ($ids[$permission] ?? null),
                ]);

                continue;
            }

            // Bez `permission_id`: sciezka i etykieta pochodza z kodu,
            // ale przypisanie uprawnienia moze byc decyzja czlowieka.
            $existing->update($attributes);
        }

        AppPage::query()
            ->whereNotIn('code', array_keys(AccessRegistry::PAGES))
            ->delete();
    }
}
