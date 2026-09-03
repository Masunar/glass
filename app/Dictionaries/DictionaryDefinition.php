<?php

declare(strict_types=1);

namespace App\Dictionaries;

use App\Enum\Permission;
use Illuminate\Database\Eloquent\Model;

/**
 * Definicja jednego słownika: model, pola, zasady porządku.
 */
final readonly class DictionaryDefinition
{
    /**
     * @param class-string<Model> $model
     * @param list<Field> $fields
     * @param list<string> $uniqueWithin kolumny zawężające unikalność nazwy
     * @param string|null $defaultScope kolumna, w obrębie której może istnieć
     *        jedna pozycja domyślna; null = jedna w całym słowniku,
     *        a brak `is_default` w polach wyłącza mechanizm
     * @param list<string> $eagerLoad
     */
    public function __construct(
        public string $slug,
        public string $label,
        public string $model,
        public array $fields,
        public Permission $permission = Permission::PARAMETERS,
        public array $uniqueWithin = [],
        public ?string $defaultScope = null,
        public array $eagerLoad = [],
        public string $orderColumn = 'position',
        public ?string $note = null,
    ) {
    }

    public function field(string $key): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function hasDefaultFlag(): bool
    {
        return $this->field('is_default') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'note' => $this->note,
            'fields' => array_map(static fn(Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}
