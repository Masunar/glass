<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Role;
use App\Models\AppPage;
use App\Support\AccessRegistry;

/**
 * Kontrola spójności uprawnień.
 *
 * To jest przeciwwaga dla dwóch decyzji z planu i bez niej obie są
 * tylko wygodne:
 *
 * **`*.access` osobno od stron (U-04)** tworzy stan, którego przy
 * prostszym wariancie by nie było: rola ma uprawnienia stron w module,
 * ale nie ma dostępu do modułu — czyli uprawnienia, które nic nie
 * robią. Człowiek klika i nie rozumie, dlaczego nic się nie dzieje.
 *
 * **Przypisanie strony w bazie (U-01)** znaczy, że nowa strona może
 * nie mieć uprawnienia. Wzorzec, z którego to wzięliśmy, uzbierał
 * w ten sposób 75 sierot — pokazywał je uczciwie w kafelku, ale nikt
 * ich nie posprzątał.
 *
 * Ta klasa **nazywa problem**, nie naprawia go. Naprawa jest decyzją
 * człowieka; automatyczne dosypanie `*.access` cicho rozszerzyłoby
 * dostęp, o który nikt nie prosił.
 */
final readonly class AccessAudit
{
    public const MODULE_WITHOUT_ACCESS = 'module_without_access';
    public const PAGE_WITHOUT_PERMISSION = 'page_without_permission';
    public const PERMISSION_PLANNED = 'permission_planned';

    public function __construct(
        private AccessResolver $resolver = new AccessResolver(),
    ) {
    }

    /**
     * Problemy roli: uprawnienia, które nic nie robią.
     *
     * @return list<array{kind: string, module: string, label: string, detail: string}>
     */
    public function forRole(Role $role): array
    {
        if ($role->is_superuser) {
            return [];
        }

        $granted = array_keys($this->resolver->forRole($role));
        $issues = [];

        /** @var array<string, list<string>> $byModule */
        $byModule = [];

        foreach ($granted as $name) {
            if (AccessRegistry::isModuleAccess($name)) {
                continue;
            }

            $module = AccessRegistry::moduleOf($name);

            if ($module !== null) {
                $byModule[$module][] = $name;
            }
        }

        foreach ($byModule as $module => $names) {
            if (in_array($module . '.' . AccessRegistry::ACCESS, $granted, true)) {
                continue;
            }

            $issues[] = [
                'kind' => self::MODULE_WITHOUT_ACCESS,
                'module' => $module,
                'label' => AccessRegistry::MODULES[$module] ?? $module,
                'detail' => sprintf(
                    '%d uprawnień w tym module nie działa, bo rola nie ma dostępu do samego modułu.',
                    count($names),
                ),
            ];
        }

        return $issues;
    }

    /**
     * Problemy konfiguracji, niezależne od roli.
     *
     * @return list<array{kind: string, module: string|null, label: string, detail: string}>
     */
    public function system(): array
    {
        $issues = [];

        /** @var iterable<AppPage> $pages */
        $pages = AppPage::query()->orderBy('position')->get();

        foreach ($pages as $page) {
            // Pulpit swiadomie nie ma uprawnienia — jest dla kazdego
            // zalogowanego. Rejestr o tym wie, wiec nie krzyczymy.
            $expected = AccessRegistry::pages()[$page->code]['permission'] ?? null;

            if ($page->permission_id !== null || $expected === null) {
                continue;
            }

            $issues[] = [
                'kind' => self::PAGE_WITHOUT_PERMISSION,
                'module' => $page->module,
                'label' => $page->label,
                'detail' => 'Strona nie ma przypisanego uprawnienia — otworzy ją każdy zalogowany.',
            ];
        }

        foreach (AccessRegistry::permissions() as $definition) {
            if ($definition['state'] !== AccessRegistry::PLANNED) {
                continue;
            }

            $issues[] = [
                'kind' => self::PERMISSION_PLANNED,
                'module' => $definition['module'],
                'label' => $definition['label'],
                // Bez domyslki: wpis PLANNED ma powod, a pilnuje tego
                // `AccessCoverageTest`.
                'detail' => $definition['note'],
            ];
        }

        return $issues;
    }
}
