<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Support\AccessRegistry;
use App\Models\PermissionPackage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Validator;

/**
 * Zapis uprawnień roli, paczek i nadań ponad rolę.
 *
 * Każdy zapis wraca z **bilansem** — ile nadanych, ile doszło, ile
 * zniknęło. „Zapisano" nie mówi nic o operacji, po której chce się
 * wiedzieć dokładnie, co się zmieniło; wzorzec, z którego to wzięliśmy,
 * ma ten bilans w stopce i to jest jego najlepszy pomysł.
 *
 * Roli nadrzędnej nie da się konfigurować. Omija sprawdzanie przez
 * `Gate::before`, więc odznaczanie jej uprawnień byłoby teatrem:
 * ekran pokazywałby brak, a człowiek dalej by wchodził wszędzie. To
 * samo dotyczy użytkownika, który taką rolę ma.
 */
final readonly class AccessService
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
        private AccessResolver $resolver = new AccessResolver(),
    ) {
    }

    /**
     * Uprawnienia i paczki roli.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, balance: array{granted: int, added: int, removed: int}|null}
     */
    public function saveRole(int $roleId, array $input): array
    {
        /** @var Role $role */
        $role = Role::query()->with(['permissions', 'packages'])->findOrFail($roleId);

        if ($role->is_superuser) {
            return [
                'errors' => ['role' => [
                    'Rola nadrzędna omija sprawdzanie uprawnień w całości — konfigurowanie jej niczego by nie zmieniło.',
                ]],
                'balance' => null,
            ];
        }

        $validator = Validator::make($input, [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
            'packages' => ['present', 'array'],
            'packages.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'balance' => null];
        }

        /** @var list<string> $wanted */
        $wanted = array_values(array_unique(array_map('strval', (array) $input['permissions'])));
        $unknown = array_diff($wanted, AccessRegistry::names());

        if ($unknown !== []) {
            // Nazwa spoza rejestru to uprawnienie, ktorego nikt nie
            // sprawdza — nadanie go byloby zapisem bez skutku.
            return [
                'errors' => ['permissions' => [
                    'Nieznane uprawnienia: ' . implode(', ', $unknown),
                ]],
                'balance' => null,
            ];
        }

        $before = $role->permissions->pluck('name')->all();
        $attached = array_map('intval', (array) $input['packages']);

        // Rola trzyma **wylacznie to, czego nie daje jej zadna paczka**.
        // Ekran przysyla komplet zaznaczen, bo pokazuje pokrycie razem
        // z paczkami; zapisanie tego wprost zamienialoby wiazanie
        // w kopie z chwili zapisu i poprawiona paczka przestalaby
        // cokolwiek zmieniac przy tej roli (U-07).
        $own = array_values(array_diff($wanted, $this->fromPackages($attached)));

        $role->syncPermissions($own);
        $role->packages()->sync($attached);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($own, $before));
        $removed = array_values(array_diff($before, $own));

        $this->write(
            $role,
            'uprawnienia',
            count($before) . ' nadanych',
            count($own) . ' nadanych',
            $added,
            $removed,
        );

        // Bilans mowi o nadaniach wlasnych roli. Uprawnienia z paczek
        // nie sa jej nadaniami i nie da sie ich tu odebrac — zmienia je
        // paczka albo odpiecie paczki.
        return [
            'errors' => [],
            'balance' => [
                'granted' => count($own),
                'added' => count($added),
                'removed' => count($removed),
            ],
        ];
    }

    /**
     * Nazwy uprawnień, które dają wskazane paczki.
     *
     * @param list<int> $packageIds
     * @return list<string>
     */
    private function fromPackages(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        $names = [];

        /** @var iterable<PermissionPackage> $packages */
        $packages = PermissionPackage::query()
            ->with('permissions')
            ->whereIn('id', $packageIds)
            ->get();

        foreach ($packages as $package) {
            foreach ($package->permissions as $permission) {
                $names[] = (string) $permission->name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Uprawnienia nadane użytkownikowi **ponad rolę** (U-05).
     *
     * Suma, nie różnica: przy użytkowniku da się dodać, nie da się
     * odebrać. Mieszanie obu kierunków dałoby stan, w którym o dostępie
     * decyduje kolejność czytania reguł — a wtedy na pytanie „dlaczego
     * on to widzi" nie da się odpowiedzieć bez czytania kodu.
     *
     * Nadanie tego, co i tak daje rola, jest **odrzucane w ciszy**:
     * zostawione, zamieniłoby się w drugie źródło tego samego dostępu,
     * które przetrwa odebranie uprawnienia roli. Tak powstają konta,
     * które po degradacji dalej wszystko widzą.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, balance: array{granted: int, added: int, removed: int, skipped: int}|null}
     */
    public function saveUser(int $userId, array $input): array
    {
        /** @var User $user */
        $user = User::query()
            ->with(['roles.permissions', 'roles.packages.permissions', 'permissions'])
            ->findOrFail($userId);

        if ($user->isSuperUser()) {
            return [
                'errors' => ['user' => [
                    'Ten użytkownik ma rolę nadrzędną, która omija sprawdzanie uprawnień — nadanie czegokolwiek ponad nią niczego by nie zmieniło.',
                ]],
                'balance' => null,
            ];
        }

        $validator = Validator::make($input, [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'balance' => null];
        }

        /** @var list<string> $wanted */
        $wanted = array_values(array_unique(array_map('strval', (array) $input['permissions'])));
        $unknown = array_diff($wanted, AccessRegistry::names());

        if ($unknown !== []) {
            return [
                'errors' => ['permissions' => [
                    'Nieznane uprawnienia: ' . implode(', ', $unknown),
                ]],
                'balance' => null,
            ];
        }

        $fromRoles = [];

        foreach ($user->roles as $role) {
            foreach (array_keys($this->resolver->forRole($role)) as $name) {
                $fromRoles[] = $name;
            }
        }

        $kept = array_values(array_diff($wanted, $fromRoles));
        $skipped = count($wanted) - count($kept);

        $before = $user->permissions->pluck('name')->all();

        $user->syncPermissions($kept);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($kept, $before));
        $removed = array_values(array_diff($before, $kept));

        $this->writeUser($user, count($before), count($kept), $added, $removed);

        return [
            'errors' => [],
            'balance' => [
                'granted' => count($kept),
                'added' => count($added),
                'removed' => count($removed),
                // Pominiete nie sa bledem, ale milczenie o nich
                // wygladaloby jak zgubiony klik.
                'skipped' => $skipped,
            ],
        ];
    }

    /**
     * Zapis paczki.
     *
     * Paczka jest **wiązaniem**, więc ta jedna operacja zmienia każdą
     * rolę, która ją ma. Bilans zwraca `roles` właśnie po to: ekran ma
     * powiedzieć, kogo dotknęła zmiana, a nie tylko że się udała.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null, balance: array{granted: int, added: int, removed: int, roles: int}|null}
     */
    public function savePackage(?int $packageId, array $input): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:250'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.required' => 'Paczka bez nazwy nie da się nadać świadomie.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null, 'balance' => null];
        }

        /** @var list<string> $wanted */
        $wanted = array_values(array_unique(array_map('strval', (array) $input['permissions'])));
        $unknown = array_diff($wanted, AccessRegistry::names());

        if ($unknown !== []) {
            return [
                'errors' => ['permissions' => ['Nieznane uprawnienia: ' . implode(', ', $unknown)]],
                'id' => null,
                'balance' => null,
            ];
        }

        $package = $packageId === null
            ? new PermissionPackage()
            : PermissionPackage::query()->with('permissions')->findOrFail($packageId);

        $before = $packageId === null ? [] : $package->permissions->pluck('name')->all();

        $package->fill([
            'name' => (string) $input['name'],
            'description' => $input['description'] === null ? null : (string) $input['description'],
        ])->save();

        /** @var list<int> $ids */
        $ids = Permission::query()->whereIn('name', $wanted)->pluck('id')->all();
        $package->permissions()->sync($ids);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($wanted, $before));
        $removed = array_values(array_diff($before, $wanted));
        $roles = $package->roles()->count();

        $this->writePackage($package, $added, $removed, $roles);

        return [
            'errors' => [],
            'id' => (int) $package->getKey(),
            'balance' => [
                'granted' => count($wanted),
                'added' => count($added),
                'removed' => count($removed),
                'roles' => $roles,
            ],
        ];
    }

    /**
     * Usunięcie paczki.
     *
     * Paczka używana przez role nie znika po cichu: jej skasowanie
     * odebrałoby uprawnienia komuś, kto o tym nie wie. Najpierw trzeba
     * ją odpiąć — to jest ta sama decyzja, tylko widoczna.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function deletePackage(int $packageId): array
    {
        /** @var PermissionPackage $package */
        $package = PermissionPackage::query()->withCount('roles')->findOrFail($packageId);

        if ((int) ($package->roles_count ?? 0) > 0) {
            return ['errors' => ['package' => [
                'Tę paczkę ma ' . $package->roles_count . ' ról. Odepnij ją od nich, zanim skasujesz.',
            ]]];
        }

        $name = $package->name;
        $package->delete();

        $this->audit->write(
            PermissionPackage::class,
            $packageId,
            [['field' => 'paczka', 'before' => $name, 'after' => null]],
            'package_deleted',
        );

        return ['errors' => []];
    }

    /**
     * @param list<string> $added
     * @param list<string> $removed
     */
    private function write(Role $role, string $field, string $before, string $after, array $added, array $removed): void
    {
        $changes = [['field' => $field, 'before' => $before, 'after' => $after]];

        // Sama liczba nie wystarczy: „11 nadanych" nie mowi, ktore
        // doszly. Po pol roku pytanie brzmi „kto dal mu magazyn".
        if ($added !== []) {
            $changes[] = ['field' => 'dodane', 'before' => null, 'after' => implode(', ', $added)];
        }

        if ($removed !== []) {
            $changes[] = ['field' => 'usunięte', 'before' => implode(', ', $removed), 'after' => null];
        }

        $this->audit->write(Role::class, (int) $role->getKey(), $changes, 'role_permissions_changed');
    }

    /**
     * @param list<string> $added
     * @param list<string> $removed
     */
    private function writeUser(User $user, int $before, int $after, array $added, array $removed): void
    {
        $changes = [[
            'field' => 'uprawnienia ponad rolę',
            'before' => $before . ' nadanych',
            'after' => $after . ' nadanych',
        ]];

        if ($added !== []) {
            $changes[] = ['field' => 'dodane', 'before' => null, 'after' => implode(', ', $added)];
        }

        if ($removed !== []) {
            $changes[] = ['field' => 'usunięte', 'before' => implode(', ', $removed), 'after' => null];
        }

        $this->audit->write(User::class, (int) $user->getKey(), $changes, 'user_permissions_changed');
    }

    /**
     * @param list<string> $added
     * @param list<string> $removed
     */
    private function writePackage(PermissionPackage $package, array $added, array $removed, int $roles): void
    {
        $changes = [[
            'field' => 'paczka ' . $package->name,
            'before' => null,
            // Zasieg w dzienniku, bo zmiana paczki dziala na role, ktore
            // jej nie widzialy.
            'after' => sprintf('dotyczy %d ról', $roles),
        ]];

        if ($added !== []) {
            $changes[] = ['field' => 'dodane', 'before' => null, 'after' => implode(', ', $added)];
        }

        if ($removed !== []) {
            $changes[] = ['field' => 'usunięte', 'before' => implode(', ', $removed), 'after' => null];
        }

        $this->audit->write(
            PermissionPackage::class,
            (int) $package->getKey(),
            $changes,
            'package_changed',
        );
    }
}
