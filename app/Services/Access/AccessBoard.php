<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Role;
use App\Models\AppPage;
use App\Support\AccessRegistry;
use App\Models\PermissionPackage;

/**
 * Ekrany ról, uprawnień i paczek.
 *
 * Kształt wzięty ze wzorca: kafelki modułów z licznikiem pokrycia,
 * pod nimi strony, a przy każdym zasobie grupa uprawnień z licznikiem.
 * Licznik jest tu treścią, nie ozdobą — „Strony: 6 z 18" mówi w jednym
 * spojrzeniu to, czego osiemnaście przełączników nie powie wcale.
 */
final readonly class AccessBoard
{
    public function __construct(
        private AccessResolver $resolver = new AccessResolver(),
        private AccessAudit $audit = new AccessAudit(),
    ) {
    }

    /**
     * Lista ról z tym, co widać bez wchodzenia w szczegóły.
     *
     * @return array<string, mixed>
     */
    public function roles(): array
    {
        $rows = [];

        /** @var iterable<Role> $roles */
        $roles = Role::query()->with(['permissions', 'packages'])->orderBy('name')->get();

        foreach ($roles as $role) {
            $granted = $this->resolver->forRole($role);
            $issues = $this->audit->forRole($role);

            $rows[] = [
                'id' => (int) $role->getKey(),
                'name' => $role->name,
                'is_superuser' => (bool) $role->is_superuser,
                'permissions' => count($granted),
                'packages' => $role->packages->count(),
                'issues' => count($issues),
                // Nie sama liczba: „2 problemy" kaze wejsc i sprawdzic,
                // czego dotycza. Nazwy modulow odpowiadaja na to od razu.
                'issue_labels' => array_values(array_unique(array_column($issues, 'label'))),
            ];
        }

        return ['roles' => $rows, 'system' => $this->audit->system()];
    }

    /**
     * Konfiguracja jednej roli.
     *
     * @return array<string, mixed>
     */
    public function role(int $roleId): array
    {
        /** @var Role $role */
        $role = Role::query()->with(['permissions', 'packages.permissions'])->findOrFail($roleId);

        $granted = $this->resolver->forRole($role);

        /** @var array<string, int> $pagesByModule */
        $pagesByModule = [];
        /** @var array<string, int> $coveredByModule */
        $coveredByModule = [];

        /** @var iterable<AppPage> $pages */
        $pages = AppPage::query()->with('permission')->orderBy('position')->get();
        $pageRows = [];

        foreach ($pages as $page) {
            $module = $page->module;
            $name = $page->permission?->name;
            // Strona bez uprawnienia otwiera sie kazdemu zalogowanemu,
            // wiec liczy sie jako pokryta — inaczej pulpit wygladalby
            // na brak, ktorego nie da sie uzupelnic.
            $isOpen = $name === null || array_key_exists($name, $granted);

            if ($module !== null) {
                $pagesByModule[$module] = ($pagesByModule[$module] ?? 0) + 1;

                if ($isOpen) {
                    $coveredByModule[$module] = ($coveredByModule[$module] ?? 0) + 1;
                }
            }

            $pageRows[] = [
                'code' => $page->code,
                'path' => $page->path,
                'module' => $module,
                'label' => $page->label,
                'permission' => $name,
                'is_open' => $isOpen,
                'is_public' => $name === null,
            ];
        }

        $modules = [];

        foreach (AccessRegistry::MODULES as $key => $label) {
            $access = $key . '.' . AccessRegistry::ACCESS;

            $modules[] = [
                'key' => $key,
                'label' => $label,
                'access_permission' => $access,
                'has_access' => array_key_exists($access, $granted),
                'access_origin' => $this->origin($granted, $access),
                'pages' => $pagesByModule[$key] ?? 0,
                'pages_covered' => $coveredByModule[$key] ?? 0,
            ];
        }

        return [
            'role' => [
                'id' => (int) $role->getKey(),
                'name' => $role->name,
                // Rola nadrzedna omija sprawdzanie w calosci, wiec ekran
                // ma o tym powiedziec, zamiast pokazywac wszystko
                // zaznaczone i udawac, ze da sie to odznaczyc.
                'is_superuser' => (bool) $role->is_superuser,
            ],
            'modules' => $modules,
            'pages' => $pageRows,
            'groups' => $this->groups($granted),
            'packages' => $this->packagesFor($role),
            'issues' => $this->audit->forRole($role),
        ];
    }

    /**
     * Grupy uprawnień per zasób, z licznikiem i pochodzeniem.
     *
     * @param array<string, list<array{from: string, name: string|null}>> $granted
     * @return list<array<string, mixed>>
     */
    private function groups(array $granted): array
    {
        $groups = [];

        foreach (AccessRegistry::permissions() as $key => $definition) {
            $items = [];
            $count = 0;

            foreach ($definition['subs'] as $sub) {
                $name = $key . '.' . $sub;
                $has = array_key_exists($name, $granted);
                $count += $has ? 1 : 0;

                $items[] = [
                    'name' => $name,
                    'sub' => $sub,
                    'granted' => $has,
                    'origin' => $this->origin($granted, $name),
                ];
            }

            $groups[] = [
                'key' => $key,
                'label' => $definition['label'],
                'module' => $definition['module'],
                'page' => $definition['page'],
                'state' => $definition['state'],
                'note' => $definition['note'],
                'granted' => $count,
                'total' => count($definition['subs']),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @param array<string, list<array{from: string, name: string|null}>> $granted
     */
    private function origin(array $granted, string $name): ?string
    {
        if (!array_key_exists($name, $granted)) {
            return null;
        }

        return $this->resolver->describe($granted[$name]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packagesFor(Role $role): array
    {
        $mine = $role->packages->pluck('id')->all();
        $rows = [];

        /** @var iterable<PermissionPackage> $packages */
        $packages = PermissionPackage::query()->withCount(['permissions', 'roles'])->orderBy('name')->get();

        foreach ($packages as $package) {
            $rows[] = [
                'id' => (int) $package->getKey(),
                'name' => $package->name,
                'description' => $package->description,
                'permissions' => (int) ($package->permissions_count ?? 0),
                'roles' => (int) ($package->roles_count ?? 0),
                'attached' => in_array($package->getKey(), $mine, true),
            ];
        }

        return $rows;
    }
}
