<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Location;
use App\Models\AuditEntry;
use Illuminate\Support\Str;
use App\Models\Workstation;
use App\Dictionaries\Field;
use App\Dictionaries\FieldType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use App\Dictionaries\DictionaryRegistry;
use App\Dictionaries\DictionaryDefinition;

/**
 * Odczyt i zapis słowników prostych.
 *
 * Jeden serwis obsługuje wszystkie pozycje rejestru, bo różnicę niesie
 * definicja, nie kod. Trzy reguły są wspólne dla każdego słownika
 * i dlatego siedzą tutaj, a nie w siedmiu kontrolerach:
 *
 * 1. Nie usuwamy, tylko dezaktywujemy. Braki w numeracji starego
 *    słownika typów szkła (S-15) wskazują na twarde usunięcia, przez
 *    które historyczne zlecenia wskazują na nieistniejącą pozycję.
 *
 * 2. Nazwa jest wymagana i unikalna. Stary słownik zestawów okuć
 *    zawierał pozycje „1”, „140”, „test” i dwa razy to samo nazwisko.
 *
 * 3. Pozycja porządkowa nadawana automatycznie. Ręczne numery
 *    kolidowały ze sobą (dwie jedynki, dwie dwunastki), więc kolejność
 *    listy była niedeterministyczna.
 */
final readonly class DictionaryService
{
    public function __construct(
        private DictionaryRegistry $registry,
    ) {
    }

    /**
     * Opis wszystkich słowników — z tego frontend buduje zakładki,
     * kolumny i formularze.
     *
     * @return list<array<string, mixed>>
     */
    public function describe(): array
    {
        $described = [];

        foreach ($this->registry->all() as $definition) {
            $entry = $definition->toArray();
            $entry['fields'] = array_map(
                fn(Field $field): array => $this->describeField($field),
                $definition->fields,
            );

            $described[] = $entry;
        }

        return $described;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $slug, bool $includeInactive = false): array
    {
        $definition = $this->definition($slug);
        $model = $definition->model;

        /** @var Builder<Model> $query */
        $query = $model::query();

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        if ($definition->eagerLoad !== []) {
            $query->with($definition->eagerLoad);
        }

        // Bez Larastan sygnatura `get()` widziana jest jako kolekcja
        // stdClass — adnotacja przywraca faktyczny typ.
        /** @var iterable<Model> $records */
        $records = $query
            ->orderBy($definition->orderColumn)
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($records as $record) {
            $rows[] = $this->row($definition, $record);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function save(string $slug, array $input, ?int $id = null): array
    {
        $definition = $this->definition($slug);
        $model = $definition->model;

        /** @var Model $record */
        $record = $id === null ? new $model() : $model::query()->findOrFail($id);

        $rules = [];

        foreach ($definition->fields as $field) {
            // Edycja jest cząstkowa: pole nieprzysłane zostaje bez zmian,
            // więc nie może być wymagane. Inaczej zmiana samej nazwy
            // wymagałaby odesłania całego wiersza z powrotem.
            if ($record->exists && !array_key_exists($field->key, $input)) {
                continue;
            }

            $rules[$field->key] = $field->rules();
        }

        $validator = Validator::make($input, $rules, [], $this->attributeNames($definition));

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $nameError = $this->checkName($definition, $input, $record);

        if ($nameError !== []) {
            return ['errors' => $nameError, 'id' => null];
        }

        $tracked = $this->trackedColumns($definition);
        $before = $id === null ? null : $record->only($tracked);

        $record->fill($this->attributes($definition, $input, $record));

        if ($id === null) {
            $record->setAttribute(
                $definition->orderColumn,
                (int) $model::query()->max($definition->orderColumn) + 10,
            );
        }

        $record->save();

        $this->enforceSingleDefault($definition, $record);

        $this->audit($definition->model, (int) $record->getKey(), $before, $record->only($tracked));

        return ['errors' => [], 'id' => (int) $record->getKey()];
    }

    /**
     * Dezaktywacja zamiast usunięcia — pozycja znika z nowych zleceń,
     * ale historia dalej się rozwiązuje.
     */
    public function deactivate(string $slug, int $id): void
    {
        $definition = $this->definition($slug);

        $model = $definition->model;

        /** @var Model $record */
        $record = $model::query()->findOrFail($id);

        if ($record->getAttribute('is_active') === false) {
            return;
        }

        $record->setAttribute('is_active', false);
        $record->save();

        $this->audit(
            $definition->model,
            $id,
            ['is_active' => true],
            ['is_active' => false],
        );
    }

    /**
     * Listy wyboru dla pól typu REFERENCE. Nazwy źródeł są stałe,
     * bo definicja słownika ma być danymi, a nie domknięciem.
     *
     * @return list<array{value: string, label: string}>
     */
    public function optionsFor(string $source): array
    {
        return match ($source) {
            'locations' => $this->optionsFrom(Location::class, 'position'),
            'workstations' => $this->optionsFrom(Workstation::class, 'position'),
            // Użytkownik nie ma kolumny `name` — etykieta składa się
            // z imienia i nazwiska, a sortowanie idzie po kolumnie,
            // która w tabeli faktycznie istnieje.
            'users' => $this->optionsFrom(User::class, 'first_name', ['first_name', 'last_name']),
            default => [],
        };
    }

    /**
     * @param class-string<Model> $model
     * @param list<string> $labelColumns
     * @return list<array{value: string, label: string}>
     */
    private function optionsFrom(string $model, string $orderColumn, array $labelColumns = ['name']): array
    {
        /** @var Builder<Model> $query */
        $query = $model::query();
        $query->where('is_active', true);

        /** @var iterable<Model> $records */
        $records = $query->orderBy($orderColumn)->get();

        return $this->options($records, $labelColumns);
    }

    private function definition(string $slug): DictionaryDefinition
    {
        $definition = $this->registry->find($slug);

        if ($definition === null) {
            abort(404, 'Nieznany słownik: ' . $slug);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DictionaryDefinition $definition, Model $record): array
    {
        $row = ['id' => (int) $record->getKey()];

        foreach ($definition->fields as $field) {
            $value = $record->getAttribute($field->key);

            $row[$field->key] = match ($field->type) {
                FieldType::BOOLEAN => (bool) $value,
                FieldType::INTEGER => $value === null ? null : (int) $value,
                FieldType::SELECT => $value instanceof \BackedEnum ? (string) $value->value : $this->text($value),
                FieldType::REFERENCE => $value === null ? null : (int) $value,
                default => $this->text($value),
            };

            if ($field->type === FieldType::REFERENCE) {
                $row[$field->key . '_label'] = $this->referenceLabel($record, $field);
            }
        }

        return $row;
    }

    private function referenceLabel(Model $record, Field $field): ?string
    {
        // location_id -> location, workstation_id -> workstation
        $relation = Str::camel(Str::beforeLast($field->key, '_id'));
        $related = $record->relationLoaded($relation) ? $record->getRelation($relation) : null;

        if (!$related instanceof Model) {
            return null;
        }

        // Ta sama zasada co przy listach wyboru: nie każdy model, na
        // który wskazuje słownik, ma kolumnę `name`.
        $columns = $related->getAttribute('name') === null ? ['first_name', 'last_name'] : ['name'];
        $options = $this->options([$related], $columns);

        return $this->text($options[0]['label']);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function attributes(DictionaryDefinition $definition, array $input, Model $record): array
    {
        $attributes = [];

        foreach ($definition->fields as $field) {
            if (!array_key_exists($field->key, $input)) {
                // Pole nieobecne w żądaniu zostaje bez zmian; przy nowym
                // wierszu boolean domyka się na false, a is_active na true.
                if ($record->exists) {
                    continue;
                }

                if ($field->type === FieldType::BOOLEAN) {
                    $attributes[$field->key] = $field->key === 'is_active';
                }

                continue;
            }

            $value = $input[$field->key];

            $attributes[$field->key] = match ($field->type) {
                FieldType::BOOLEAN => (bool) $value,
                FieldType::INTEGER, FieldType::REFERENCE => $this->nullable($value) === null ? null : (int) $value,
                FieldType::DECIMAL => $this->nullable($value) === null
                    ? null
                    : number_format((float) $value, 2, '.', ''),
                default => $this->nullable($value),
            };
        }

        return $attributes;
    }

    /**
     * Pozycja domyślna jest jedna — w całym słowniku albo w obrębie
     * kolumny wskazanej przez definicję (sekcje cenowe: jedna domyślna
     * na sekcję asortymentu).
     */
    private function enforceSingleDefault(DictionaryDefinition $definition, Model $record): void
    {
        if (!$definition->hasDefaultFlag() || $record->getAttribute('is_default') !== true) {
            return;
        }

        $scope = $definition->defaultScope;
        $model = $definition->model;

        /** @var Builder<Model> $query */
        $query = $model::query();
        $query->whereKeyNot($record->getKey());

        if ($scope !== null) {
            $query->where($scope, $record->getAttribute($scope));
        }

        $query->update(['is_default' => false]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function checkName(DictionaryDefinition $definition, array $input, Model $record): array
    {
        if (!array_key_exists('name', $input)) {
            return [];
        }

        $name = trim((string) $input['name']);

        $model = $definition->model;

        /** @var Builder<Model> $taken */
        $taken = $model::query();
        $taken->where('name', $name);

        if ($record->exists) {
            $taken->whereKeyNot($record->getKey());
        }

        // Kolumna zawężająca może nie przyjść w cząstkowej edycji —
        // wtedy obowiązuje ta, którą wiersz ma teraz.
        foreach ($definition->uniqueWithin as $column) {
            $taken->where($column, $input[$column] ?? $record->getAttribute($column));
        }

        if ($taken->exists()) {
            return ['name' => ['Pozycja o tej nazwie już istnieje.']];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeField(Field $field): array
    {
        $described = $field->toArray();

        if ($field->type === FieldType::REFERENCE && $field->source !== null) {
            $described['options'] = $this->optionsFor($field->source);
        }

        return $described;
    }

    /**
     * @param iterable<Model> $records
     * @param list<string> $labelColumns
     * @return list<array{value: string, label: string}>
     */
    private function options(iterable $records, array $labelColumns = ['name']): array
    {
        $options = [];

        foreach ($records as $record) {
            $parts = [];

            foreach ($labelColumns as $column) {
                $part = $this->text($record->getAttribute($column));

                if ($part !== null) {
                    $parts[] = $part;
                }
            }

            $options[] = [
                'value' => (string) $record->getKey(),
                'label' => implode(' ', $parts),
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function trackedColumns(DictionaryDefinition $definition): array
    {
        return array_map(static fn(Field $field): string => $field->key, $definition->fields);
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(DictionaryDefinition $definition): array
    {
        $names = [];

        foreach ($definition->fields as $field) {
            $names[$field->key] = mb_strtolower($field->label);
        }

        return $names;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(string $type, int $id, ?array $before, array $after): void
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            if ($before !== null && $previous === $value) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        if ($changes === []) {
            return;
        }

        AuditEntry::query()->create([
            'edit_session_id' => (string) Str::uuid(),
            'auditable_type' => $type,
            'auditable_id' => $id,
            'user_id' => Auth::id(),
            'event' => $before === null ? 'created' : 'updated',
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
