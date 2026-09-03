<?php

declare(strict_types=1);

namespace App\Dictionaries;

/**
 * Opis jednego pola słownika.
 *
 * Frontend nie zna kolumn tabel — pobiera te opisy i z nich buduje
 * kolumny listy oraz formularz. Dodanie pola do słownika jest więc
 * zmianą w jednym miejscu, a nie w trzech.
 */
final readonly class Field
{
    /**
     * @param list<array{value: string, label: string}> $options wartości dla SELECT
     * @param string|null $source klucz listy dla REFERENCE (patrz DictionaryService::optionsFor)
     * @param list<string> $extraRules dodatkowe reguły walidacyjne Laravela
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type = FieldType::TEXT,
        public bool $required = false,
        public ?int $max = null,
        public array $options = [],
        public ?string $source = null,
        public array $extraRules = [],
        /** Pole widoczne na liście, a nie tylko w formularzu edycji. */
        public bool $inList = true,
        public ?string $hint = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        $rules = match ($this->type) {
            FieldType::TEXT => [...$rules, 'string', 'max:' . ($this->max ?? 255)],
            FieldType::INTEGER => [...$rules, 'integer', 'min:0', 'max:' . ($this->max ?? 65535)],
            FieldType::DECIMAL => [...$rules, 'numeric', 'min:0', 'max:' . ($this->max ?? 9999999)],
            FieldType::BOOLEAN => [...$rules, 'boolean'],
            FieldType::SELECT => [...$rules, 'string', 'in:' . implode(',', array_column($this->options, 'value'))],
            FieldType::REFERENCE => [...$rules, 'integer'],
        };

        return [...$rules, ...$this->extraRules];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->options,
            'in_list' => $this->inList,
            'hint' => $this->hint,
        ];
    }
}
