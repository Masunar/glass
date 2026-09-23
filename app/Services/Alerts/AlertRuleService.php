<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Models\Status;
use App\Models\AlertRule;
use App\Enum\StatusDomain;
use App\Enum\AlertCategory;
use App\Services\AuditTrail;
use App\Alerts\ConditionCatalog;
use App\Models\AlertOccurrence;
use App\Support\AccessRegistry;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reguły alertów — ekran administratora.
 *
 * Administrator ustawia **parametry typu, etykietę, kolor, moduł
 * i włączenie**. Typu warunku nie da się wymyślić: pochodzi z katalogu
 * (`ConditionCatalog`), a zapis odrzuca wszystko, czego katalog nie zna.
 * Reguła, której nikt nie umie policzyć, nie może powstać.
 */
final readonly class AlertRuleService
{
    public function __construct(
        private ConditionCatalog $catalog = new ConditionCatalog(),
        private AlertEngine $engine = new AlertEngine(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        /** @var Collection<int, AlertRule> $rules */
        $rules = AlertRule::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var array<int, int> $open */
        $open = AlertOccurrence::query()
            ->whereNull('resolved_at')
            ->selectRaw('alert_rule_id, count(*) as total')
            ->groupBy('alert_rule_id')
            ->pluck('total', 'alert_rule_id')
            ->all();

        $rows = [];

        foreach ($rules as $rule) {
            /** @var array<string, mixed> $condition */
            $condition = $rule->condition;
            $type = is_string($condition['type'] ?? null) ? $condition['type'] : '';

            $rows[] = [
                'id' => (int) $rule->getKey(),
                'code' => $rule->code,
                'name' => $rule->name,
                'module' => $rule->module,
                'category' => $rule->category->value,
                'type' => $type,
                // Nazwa typu, ktorej katalog nie zna, jest widoczna na
                // ekranie jako brak — regula zepsuta ma wygladac na
                // zepsuta, a nie na wylaczona.
                'type_label' => $this->catalog->find($type)?->label(),
                'params' => $condition,
                'label' => $rule->label,
                'color' => $rule->color,
                'position' => $rule->position,
                'is_active' => (bool) $rule->is_active,
                'open' => $rule->is_active ? ($open[(int) $rule->getKey()] ?? 0) : null,
            ];
        }

        return [
            'rules' => $rows,
            'catalog' => $this->catalog->board(),
            'statuses' => $this->statuses(),
            'modules' => $this->modules(),
            'categories' => $this->categories(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function create(array $input): array
    {
        $result = $this->validated($input, null);

        if ($result['errors'] !== []) {
            return ['errors' => $result['errors'], 'id' => null];
        }

        $rule = new AlertRule();
        /** @var array<string, mixed> $data */
        $data = $result['data'];
        $rule->fill($data);
        $rule->save();

        $this->audit->record(AlertRule::class, (int) $rule->getKey(), null, $this->tracked($rule));
        $this->engine->forget();

        return ['errors' => [], 'id' => (int) $rule->getKey()];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function update(AlertRule $rule, array $input): array
    {
        $result = $this->validated($input, $rule);

        if ($result['errors'] !== []) {
            return ['errors' => $result['errors'], 'id' => null];
        }

        $before = $this->tracked($rule);
        $wasActive = (bool) $rule->is_active;

        /** @var array<string, mixed> $data */
        $data = $result['data'];
        $rule->fill($data);
        $rule->save();

        $after = $this->tracked($rule);
        $this->audit->record(AlertRule::class, (int) $rule->getKey(), $before, $after);

        // Wylaczenie reguly albo zmiana jej warunku uniewaznia otwarte
        // wystapienia: zostawione, wisialyby na ekranie jako alerty,
        // ktorych nic juz nie przelicza.
        if (($wasActive && !$rule->is_active) || $before['params'] !== $after['params']) {
            $this->engine->close($rule);
        }

        $this->engine->forget();

        return ['errors' => [], 'id' => (int) $rule->getKey()];
    }

    public function delete(AlertRule $rule): void
    {
        $this->audit->write(
            AlertRule::class,
            (int) $rule->getKey(),
            [['field' => 'code', 'before' => $rule->code, 'after' => null]],
            'deleted',
        );

        // Wystapienia znikaja razem z regula (kaskada w migracji) — alert
        // bez reguly nie ma etykiety ani koloru, wiec nie ma czym byc.
        $rule->delete();
        $this->engine->forget();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, data: array<string, mixed>}
     */
    private function validated(array $input, ?AlertRule $rule): array
    {
        $errors = [];

        $code = $this->text($input['code'] ?? null);
        $name = $this->text($input['name'] ?? null);
        $label = $this->text($input['label'] ?? null);
        $type = $this->text($input['type'] ?? null);
        $module = $this->text($input['module'] ?? null);
        $color = $this->text($input['color'] ?? null);

        if ($code === null) {
            $errors['code'] = ['Kod reguły jest wymagany.'];
        } elseif (preg_match('/^[a-z0-9_]{3,60}$/', $code) !== 1) {
            $errors['code'] = ['Kod może zawierać małe litery, cyfry i podkreślenie (3–60 znaków).'];
        } elseif ($this->codeTaken($code, $rule)) {
            $errors['code'] = ['Reguła o tym kodzie już istnieje.'];
        }

        if ($name === null) {
            $errors['name'] = ['Nazwa jest wymagana.'];
        }

        if ($label === null) {
            $errors['label'] = ['Etykieta jest wymagana — to ona staje w wierszu zlecenia.'];
        }

        $condition = $type === null ? null : $this->catalog->find($type);

        if ($condition === null) {
            $errors['type'] = ['Wybierz typ warunku z katalogu.'];
        }

        if ($module !== null && !array_key_exists($module, AccessRegistry::MODULES)) {
            $errors['module'] = ['Nieznany moduł.'];
        }

        if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            $errors['color'] = ['Kolor podaj w formacie #rrggbb albo zostaw pusty.'];
        }

        if ($errors !== [] || $condition === null) {
            return ['errors' => $errors, 'data' => []];
        }

        /** @var array<string, mixed> $params */
        $params = is_array($input['params'] ?? null) ? $input['params'] : [];

        // Pozycja nie jest polem formularza, wiec zapis nie moze jej
        // zerowac: regula edytowana zostaje na swoim miejscu, nowa idzie
        // na koniec. Kolejnosc rzadzi kolejnoscia w pasmie na pulpicie.
        $position = $input['position'] ?? null;

        if (!is_int($position) && !(is_string($position) && ctype_digit($position))) {
            $position = $rule === null ? $this->nextPosition() : $rule->position;
        }

        return ['errors' => [], 'data' => [
            'code' => $code,
            'name' => $name,
            'label' => $label,
            'color' => $color,
            // Modul i kategoria ida z typu warunku, o ile administrator
            // nie wskaze inaczej: alert o zleceniu nalezy do Zlecen, i to
            // nie jest jego decyzja.
            'module' => $module ?? $condition->module(),
            'category' => $condition->category()->value,
            'condition' => $this->catalog->normalize($condition, $params),
            'position' => (int) $position,
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]];
    }

    private function nextPosition(): int
    {
        return ((int) AlertRule::query()->max('position')) + 10;
    }

    private function codeTaken(string $code, ?AlertRule $rule): bool
    {
        $query = AlertRule::query()->where('code', $code);

        if ($rule !== null) {
            $query->whereKeyNot($rule->getKey());
        }

        return $query->exists();
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function tracked(AlertRule $rule): array
    {
        return [
            'code' => $rule->code,
            'name' => $rule->name,
            'label' => $rule->label,
            'module' => $rule->module,
            'color' => $rule->color,
            'is_active' => (bool) $rule->is_active,
            'params' => $rule->condition,
        ];
    }

    /**
     * Statusy zleceń jako opcje parametru — z tego samego słownika, co
     * zakładki listy.
     *
     * @return list<array{code: string, name: string}>
     */
    private function statuses(): array
    {
        /** @var Collection<int, Status> $statuses */
        $statuses = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $rows = [];

        foreach ($statuses as $status) {
            $rows[] = ['code' => $status->code, 'name' => $status->name];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    private function modules(): array
    {
        $rows = [];

        foreach (AccessRegistry::MODULES as $key => $name) {
            $rows[] = ['key' => $key, 'name' => $name];
        }

        return $rows;
    }

    /**
     * @return list<array{value: string, name: string}>
     */
    private function categories(): array
    {
        return [
            ['value' => AlertCategory::DEADLINE->value, 'name' => 'Termin'],
            ['value' => AlertCategory::PAYMENTS->value, 'name' => 'Płatności'],
            ['value' => AlertCategory::MISSING_DATA->value, 'name' => 'Braki'],
            ['value' => AlertCategory::APPROVAL->value, 'name' => 'Akceptacja'],
            ['value' => AlertCategory::QUALITY->value, 'name' => 'Jakość'],
        ];
    }
}
